<?php

require_once(dirname(__FILE__).'/../common.php');
require_once(dirname(__FILE__)."/../../../config/config.php");
require_once(dirname(__FILE__)."/../sort_methods/sort_order_status.php");
require_once(dirname(__FILE__).'/functions.php');

function do_grouping_same_host_name(&$statuses) {
	global $config, $l, $tmp_direction;

	$l = get_logger("dashboard");

    # rough tasks
	# build a temporary hash with the service names
	# find the ones that have 1+ entry
	# use the md5hash of the entries based on 'group!$service'
	# build the new hash
	# return the new hash

    $statuses_result = Array();
    $statuses_grouping = Array();
    $ignored = Array();
    foreach ($statuses["status"] as $md5 => $state_data) {
        if ($statuses["status"][$md5]["type"] != "service") {
            $ignored[$md5] = $state_data;
            continue;
        }
        # ignore already grouped
        if (array_key_exists("is_grouped", $statuses["status"][$md5])) {
            if ($statuses["status"][$md5]["is_grouped"] === true) {
                $ignored[$md5] = $state_data;
                continue;
            }
        }
        $host = $statuses["status"][$md5]["host"];
        if (!array_key_exists($host, $statuses_grouping)) {
            $statuses_grouping[$host] = Array();
        }
        # gather all the md5s of the services that needs to be grouped under the same service name
        array_push($statuses_grouping[$host], $md5);
	}

    # delete the the original statuses
    $statuses_result = $statuses;
    $statuses_result["status"] = Array();

    # go through the $statuses_grouping array, and create a new $statuses array based on it
    foreach ($statuses_grouping as $host => $group) {
        if (count($group) > 1) {
            # get new md5
            $md5_grouped = md5("group!" . $host);

            # what to display as service?
            $service = $config["grouping"]["host"]["group_service_name"]["default"];

            # if one is critical, this is critical
            $status = get_common_status($statuses, $group);
            # get the duration
            $duration_seconds = get_common_value($statuses, $group, "duration", $config["grouping"]["duration"]["show_newest"]);
            $duration = convert_seconds_to_duration($duration_seconds);
            # flapping? if one flapping, this is flapping
            $is_flapping = get_common_value($statuses, $group, "is_flapping", false);
            # alert_active? if one is active, this is active
            $alert_active = get_common_value($statuses, $group, "alert_active", false);
            # soft? only if all of them are soft
            $is_soft = get_common_value($statuses, $group, "is_soft", true);
            # get the oldest duration
            # get the common priority
            $priority = get_common_value($statuses, $group, "priority", $config["sort_priority_asc"]);

            # put all of them into an array so it can be displayed later
            $statuses_result["status"][$md5_grouped]["host"] = $host;
            $statuses_result["status"][$md5_grouped]["service"] = $service;
            $statuses_result["status"][$md5_grouped]["status_information"] = "N/A";
            $statuses_result["status"][$md5_grouped]["status"] = $status;
            $statuses_result["status"][$md5_grouped]["priority"] = $priority;
            $statuses_result["status"][$md5_grouped]["duration"] = $duration;
            $statuses_result["status"][$md5_grouped]["duration_seconds"] = $duration_seconds;
            $statuses_result["status"][$md5_grouped]["md5id"] = $md5_grouped;
            $statuses_result["status"][$md5_grouped]["type"] = "service";
            $statuses_result["status"][$md5_grouped]["alert_active"] = $alert_active;
            $statuses_result["status"][$md5_grouped]["is_flapping"] = $is_flapping;
            $statuses_result["status"][$md5_grouped]["is_soft"] = $is_soft;
            $statuses_result["status"][$md5_grouped]["is_grouped"] = true;
            $statuses_result["status"][$md5_grouped]["group_size"] = count($group);
            # get the entries for this group
            $entries = get_elements_by_key($statuses["status"], $group);
            $statuses_result["status"][$md5_grouped]["group_subnodes"] = $entries;

        } else {
            # use the original md5
            $statuses_result["status"][$group[0]] = $statuses["status"][$group[0]];
        }        
    }

    # re-add the ignored ones
    foreach ($ignored as $md5 => $state_data) {
        $statuses_result["status"][$md5] = $state_data;
    }

    return $statuses_result;
}

