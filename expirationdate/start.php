<?php
/**
 * Expiration Date.
 *
 * @package ExpirationDate
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Brett Profitt
 * @copyright Brett Profitt 2008
 * @link http://eschoolconsultants.com
 */

/**
 * Initialise the plugin.
 *
 */
function expirationdate_init() {
	global $CONFIG, $config;

	// Register cron hook
	if (!elgg_get_plugin_setting('period', 'expirationdate')) {
		elgg_set_plugin_setting('period', 'fiveminute', 'expirationdate');
	}

	// override permissions for the expirationdate context
	elgg_register_plugin_hook_handler('permissions_check', 'all', 'expirationdate_permissions_check');

	elgg_register_plugin_hook_handler('cron', elgg_get_plugin_setting('period', 'expirationdate'), 'expirationdate_cron');
}

/**
 * Hook for cron event.
 *
 * @param $event
 * @param $object_type
 * @param $object
 * @return unknown_type
 */
function expirationdate_cron($event, $object_type, $object) {
	$value = expirationdate_expire_entities(false) ? 'Ok' : 'Fail';
	return 'expirationdate: ' . $value;
}

/**
 * Deletes expired entities.
 * @return boolean
 */
function expirationdate_expire_entities($verbose=true) {
	$now = time();
	$context = elgg_get_context();
	elgg_set_context('expirationdate');
	expirationdate_su();

	$access = elgg_set_ignore_access(true);

	$access_status = access_get_show_hidden_status();
	access_show_hidden_entities(true);

	if (!$entities = elgg_get_entities_from_metadata(array('metadata_names' => 'expirationdate', 'limit' => 99999))) {
		// no entities that need to expire.
		return true;
	}

	foreach ($entities as $entity) {
		if ($entity->expirationdate < $now) {
			$guid = $entity->guid;
			if (!elgg_trigger_plugin_hook('expirationdate:expire_entity', $entity->type, array('entity'=>$entity), true)) {
				continue;
			}

			// call the standard delete to allow for triggers, etc.
			if ($entity->expirationdate_disable_only == 1) {
				if ($entity->disable()) {
					$return = expirationdate_unset($entity->getGUID());
					$msg = "Disabled $guid<br>\n";
				} else {
					$msg = "Couldn't disable $guid<br>\n";
				}
			} else {
				if ($entity->delete()) {
					$msg = "Deleted $guid<br>\n";
				} else {
					$msg = "Couldn't delete $guid<br>\n";
				}
			}
		} else {
			if (!elgg_trigger_plugin_hook('expirationdate:will_expire_entity', $entity->type, array('expirationdate'=>$entity->expirationdate, 'entity'=>$entity), true)) {
				continue;
			}
		}

		if ($verbose) {
			print $msg;
		}
	}
	access_show_hidden_entities($access_status);

	elgg_set_ignore_access($access);

	elgg_set_context($context);
	expirationdate_su(true);
	return true;
}

/**
 * Sets an expiration for a GUID/Id.
 *
 * @param int $id
 * @param strToTime style date $expiration
 * @return bool
 */
function expirationdate_set($id, $expiration, $disable_only=false, $type='entities') {
	$context = elgg_get_context();
	elgg_set_context('expirationdate');

	if (!$date=strtotime($expiration)) {
		return false;
	}

	// clear out any existing expiration
	expirationdate_unset($id, $type);
	$return = false;

	if ($type == 'entities') {
		// @todo what about disabled entities?
		// Allow them to expire?
		if (!$entity=get_entity($id)) {
			return false;
		}
		$return = create_metadata($id, 'expirationdate', $date, 'integer', -1, 2);
		$return = create_metadata($id, 'expirationdate_disable_only', $disable_only, 'integer', -1, 2);
	} else {
		// bugger all.
	}

	elgg_set_context($context);
	return $return;
}

/**
 * Removes an expiration date for an entry.
 *
 * @param $guid
 * @param $type
 * @return unknown_type
 */
function expirationdate_unset($id, $type='entities') {
	$context = elgg_get_context();
	elgg_set_context('expirationdate');
	expirationdate_su();
	if ($type == 'entities') {
		elgg_delete_metadata(array('guid' => $id, 'metadata_name' => 'expirationdate'));
		elgg_delete_metadata(array('guid' => $id, 'metadata_name' => 'expirationdate_disable_only'));
	}
	expirationdate_su(true);
	elgg_set_context($context);

	return true;
}


/**
 * Overrides default permissions for the expirationdate context
 *
 * @param $hook_name
 * @param $entity_type
 * @param $return_value
 * @param $parameters
 * @return unknown_type
 */
function expirationdate_permissions_check($hook_name, $entity_type, $return_value, $parameters) {
	if (elgg_get_context() == 'expirationdate') {
		return true;
	}

	return null;
}

/**
 * Elevate user to admin.
 *
 * @param bool $unsu -- Return to original permissions?
 * @return old
 */
function expirationdate_su($unsu=false) {
	global $is_admin;
	static $is_admin_orig = null;

	if (is_null($is_admin_orig)) {
		$is_admin_orig = $is_admin;
	}

	if ($unsu) {
		return $is_admin = $is_admin_orig;
	} else {
		return $is_admin = true;
	}
}

// Initialise plugin
elgg_register_event_handler('init', 'system', 'expirationdate_init');
