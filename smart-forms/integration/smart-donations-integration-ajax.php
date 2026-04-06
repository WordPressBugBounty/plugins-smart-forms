<?php

function rednao_smart_forms_get_campaigns()
{
    check_ajax_referer('rednao_smart_forms_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access', 403);
        return;
    }

    global $wpdb;
    $campaigns=$wpdb->get_results("select campaign_id, name from ".SMART_DONATIONS_CAMPAIGN_TABLE,'ARRAY_A');
    array_splice($campaigns,0,0,array(array(
                                    "campaign_id"=>"",
                                    "name"=>__("None"))
    ));
    echo json_encode($campaigns);
    die();
}