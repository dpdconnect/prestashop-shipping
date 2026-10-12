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
 * The async callback endpoint now requires a shared secret. Generate it here so
 * existing shops have a token before the next batch is queued — createUrl()
 * would otherwise create it lazily on first use, which works but means the
 * token is created during an API call instead of during the upgrade.
 *
 * Any batch that is still queued at upgrade time was handed an old, tokenless
 * callback URL. Those callbacks will be rejected with a 403, so their jobs stay
 * queued and need to be regenerated from the Batches screen.
 *
 * @param dpdconnect $module
 *
 * @return bool
 */
function upgrade_module_2_4_0($module)
{
    DpdLabelGenerator::getCallbackToken();

    return $module->registerHook('actionValidateOrder');
}
