<?php
/*
 * Plugin Name: WP Courseware - Paid Memberships Pro Add On
 * Version: 1.2
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for WP Courseware to add support for the Paid Memberships Pro membership plugin for WordPress.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */

// Main parent class
include_once 'class_members.inc.php';

/**
 *Add the PMPro meta box to a course units
 */
 
//Hook to add metabox to course unit
	if (is_admin())
	{
		add_action('add_meta_boxes', 'pmpro_cpt_mbox', 20);
	}

	function pmpro_cpt_mbox()
	{
			//duplicate this row for each CPT
		add_meta_box('pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'course_unit', 'side');	
	}

// Hook to load the class
add_action('init', 'WPCW_PMPro_init',1);

/**
 * Initialize the membership plugin, only loaded if WP Courseware 
 * exists and is loading correctly.
 */
function WPCW_PMPro_init()
{
	$item = new WPCW_PMPro();
	
	// Check for WP Courseware
	if (!$item->found_wpcourseware()) {
		$item->attach_showWPCWNotDetectedMessage();
		return;
	}
	
	// Not found the membership tool
	if (!$item->found_membershipTool()) {
		$item->attach_showToolNotDetectedMessage();
		return;
	}
	
	// Found the tool and WP Courseware, attach.
	$item->attachToTools();
}


/**
 * Membership class that handles the specifics of the Paid Memberships Pro WordPress plugin and
 * handling the data for levels for that plugin.
 */
class WPCW_PMPro extends WPCW_Members
{
	const GLUE_VERSION  = 1.00; 
	const EXTENSION_NAME = 'Paid Memberships Pro';
	const EXTENSION_ID = 'WPCW_PMPro';
	
	/**
	 * Main constructor for this class.
	 */
	function __construct()
	{
		// Initialize using the parent constructor 
		parent::__construct(WPCW_PMPro::EXTENSION_NAME, WPCW_PMPro::EXTENSION_ID, WPCW_PMPro::GLUE_VERSION);
	}
	
	
	/**
	 * Get the membership levels for Paid Memberships Pro.
	 */
	protected function getMembershipLevels()
	{	
        global $membership_levels;
        $levelData = $membership_levels;
				
		if ($levelData && count($levelData) > 0)
		{
			$levelDataStructured = array();
			
			// Format the data in a way that we expect and can process
			foreach ($levelData as $levelDatum)
			{
				$levelItem = array();
				$levelItem['name'] 	= $levelDatum->name;
				$levelItem['id'] 	= $levelDatum->id;
				$levelItem['raw'] 	= $levelDatum;
								
				$levelDataStructured[$levelItem['id']] = $levelItem;
			}
			
					
			return $levelDataStructured;
		}
		return false;
	}
	
	
	/**
	 * Function called to attach hooks for handling when a user is updated or created.
	 */	
	
	protected function attach_updateUserCourseAccess()
	{
		// Events called whenever the user levels are changed, which updates the user access.
		add_action('pmpro_after_change_membership_level', 		array($this, 'handle_updateUserCourseAccess'),10,2);

	}

	/**
	 * Assign selected courses to members of a paticular level.
	 * @param Level ID in which members will get courses enrollment adjusted.
	 */
	protected function retroactive_assignment($level_ID)
    {
    	global $wpdb;

    	$page = new PageBuilder(false);

		$query = "SELECT user_id
		      FROM $wpdb->pmpro_memberships_users
			  WHERE membership_id = $level_ID
			  AND status = 'active'";

		$results = $wpdb->get_results($query);

		if ($results){

			// Get user's assigned products and enroll them into courses accordingly
			foreach ($results as $result){
				$user = $result->user_id;	
				parent::handle_courseSync($user, array($level_ID));
			}

		$page->showMessage(__('All members were successfully retroactively enrolled into the selected courses.', 'wp_courseware'));
            
        return;

		}else {
            $page->showMessage(__('No existing customers found for the specified product.', 'wp_courseware'));
        }
    }

	/**
	 * Function just for handling the membership callback, to interpret the parameters
	 * for the class to take over.
	 * 
	 * @param Integer $id The ID if the user being changed.
	 * @param Array $levels The list of levels for the user.
	 */
	public function handle_updateUserCourseAccess($level, $user_id)
	{
		// Get user ID from transaction.
		$user = $user_id;
		// Returns an array of membership levels the user has purchased and is paid up on.
		$membership_level = $level;
		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($user, array($membership_level));
	}
		
	
	/**
	 * Detect presence of the membership plugin.
	 */
	public function found_membershipTool()
	{
		return function_exists('pmpro_activation');
	}
}
?>