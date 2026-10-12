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

use DpdConnect\classes\DpdLabelGenerator;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Make sure a callback secret exists.
 *
 * On 2.4.0 the verification path itself generated the secret when none was
 * stored, so an anonymous request to the public callback endpoint could rotate
 * it. Verification is read-only from 2.4.1 on, which means the secret has to be
 * present before the next batch is queued — createUrl() still creates it, but
 * generating it here keeps that out of the request path.
 *
 * @param dpdconnect $module
 *
 * @return bool
 */
function upgrade_module_2_4_1($module)
{
    DpdLabelGenerator::getCallbackToken();

    return true;
}
