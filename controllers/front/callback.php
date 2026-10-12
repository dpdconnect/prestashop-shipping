<?php

/**
 * This file is part of the Prestashop Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2017  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use DpdConnect\classes\JobRepo;
use DpdConnect\classes\BatchRepo;
use DpdConnect\classes\Database\LabelRepo;
use DpdConnect\classes\Connect\Label as ConnectLabel;
use DpdConnect\classes\DpdLabelGenerator;
use DpdConnect\classes\enums\JobStatus;

class dpdconnectcallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // This is a public front controller: without the shared secret the
        // endpoint is reachable by anyone, and the URL is fully predictable.
        if (!$this->isAuthenticated()) {
            header('HTTP/1.1 403 Forbidden');
            $this->ajaxDie(json_encode([
                'success' => false,
            ]));
        }

        $incomingData = json_decode(file_get_contents('php://input'), true);

        if (!is_array($incomingData) || !isset($incomingData['state'])) {
            header('HTTP/1.1 400 Bad Request');
            $this->ajaxDie(json_encode([
                'success' => false,
            ]));
        }

        // DPD may send the state as a JSON string; cast so the strict
        // comparison below cannot silently miss a valid callback.
        $state = (int) $incomingData['state'];

        /**
         * At this point, we do not care about the callback having been
         * fired
         */
        if ($state >= 16) {
            $state -= 16;
        }

        if ($state === 4) {
            self::success($incomingData);
        }

        if ($state >= 8) {
            self::failure($incomingData);
        }

        $this->ajaxDie(json_encode([
            'success' => true,
        ]));
    }


    /**
     * Verify the shared secret embedded in the callback URL handed to DPD.
     *
     * @return bool
     */
    private function isAuthenticated()
    {
        // Read-only on purpose: getCallbackToken() creates a secret when none is
        // stored, and calling it here would let an anonymous request rotate the
        // secret, invalidating every callback URL already handed to DPD.
        $expected = Configuration::get('dpdconnect_callback_token');
        $provided = Tools::getValue('token');

        if (empty($expected) || !is_string($provided)) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private static function success($incomingData)
    {
        $externalId = $incomingData['jobid'];

        $jobRepo = new JobRepo();
        $batchRepo = new BatchRepo();
        $job = $jobRepo->getByExternalId($externalId);

        // Unknown job: never fall through to the repositories with $job === false,
        // which would build "id_dpd_jobs = " and fail as a SQL syntax error.
        if (empty($job)) {
            return;
        }

        // Idempotency: DPD retries callbacks, and replaying one would insert a
        // second label row for the same order (the table has no unique key).
        if ($job['status'] === JobStatus::STATUSSUCCESS) {
            return;
        }

        // A success state without tracking info would fatal on implode() below.
        // That is a TypeError, which the catch (Exception) further down does not
        // catch, so the job would stay queued forever and DPD would keep retrying.
        $trackingInfo = isset($incomingData['shipment']['trackingInfo'])
            ? $incomingData['shipment']['trackingInfo']
            : null;

        if (!is_array($trackingInfo)
            || empty($trackingInfo['parcelNumbers'])
            || !is_array($trackingInfo['parcelNumbers'])
        ) {
            $jobRepo->updateStatus($job, JobStatus::STATUSFAILED, 'Callback contained no parcel numbers');
            $batchRepo->updateStatus($job);

            return;
        }

        // Take the order from the stored job, never from the request body: the
        // body is attacker-controlled and would let anyone target any order.
        $orderId = $job['order_id'];
        $parcelNumber = $trackingInfo['parcelNumbers'][0];
        $shipmentIdentifier = isset($trackingInfo['shipmentIdentifier']) ? $trackingInfo['shipmentIdentifier'] : '';
        $parcelNumbers = implode(',', $trackingInfo['parcelNumbers']);

        try {
            $connectLabel = new ConnectLabel();
            $labelRepo = new LabelRepo();
            $label = $connectLabel->get($parcelNumber);
            $labelId = $labelRepo->create($orderId, $label, $job['type'], $shipmentIdentifier, $parcelNumbers);

            // Mirror the synchronous flow: write the tracking number onto the order
            // and (optionally) change its status. The async/bulk path previously only
            // stored the label PDF, so bulk-generated labels never updated the order.
            DpdLabelGenerator::applyLabelToOrder(
                $orderId,
                $trackingInfo['parcelNumbers']
            );

            $jobRepo->updateStatus($job, JobStatus::STATUSSUCCESS, null, null, $labelId);
            $batchRepo->updateStatus($job);
        } catch (Exception $e) {
            $error = 'Could not download label after job completion.';
            $jobRepo->updateStatus($job, JobStatus::STATUSREQUEST, $error);
        }
    }

    private static function failure($incomingData)
    {
        $externalId = $incomingData['jobid'];

        $jobRepo = new JobRepo();
        $batchRepo = new BatchRepo();
        $job = $jobRepo->getByExternalId($externalId);

        if (empty($job)) {
            return;
        }

        $errors = isset($incomingData['error']) ? $incomingData['error'] : null;
        $stateMessage = isset($incomingData['stateMessage']) ? $incomingData['stateMessage'] : null;
        $jobRepo->updateStatus($job, JobStatus::STATUSFAILED, $stateMessage, $errors);
        $batchRepo->updateStatus($job);
    }
}
