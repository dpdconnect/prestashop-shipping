<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_2_3($module)
{
    // No schema or config changes in 2.2.3 — debug logging removed, parcelshop map URL
    // fix and oversized default package type reset already applied in 2.2.2.
    // Re-register actionValidateOrder defensively in case an earlier upgrade was skipped.
    return $module->registerHook('actionValidateOrder');
}
