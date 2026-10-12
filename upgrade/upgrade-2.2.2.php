<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_2_2($module)
{
    // Reset oversized legacy default package types. DPD rejects 100×50×50 (omvang 300 cm > 250)
    // and 150×100×100 with "Het pakket overschrijdt de maximaal toegestane afmetingen". The
    // 15×100×100 variant has odd proportions (length shorter than width/height) which can
    // also fail validation. Fall back to a safe 60×40×30 default.
    $current = Configuration::get('dpdconnect_default_package_type');
    if (in_array($current, ['100050050', '150010010', '015010010', '', false, null], true)) {
        Configuration::updateValue('dpdconnect_default_package_type', '060040030');
    }

    // Re-register actionValidateOrder defensively in case the 2.2.1 upgrade was skipped.
    return $module->registerHook('actionValidateOrder');
}
