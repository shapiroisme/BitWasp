<?php 

class Users_Model extends CI_Model {

	public function __construct() {
		parent::__construct();
		$this->load->library('session');
	}

	// Check the submitted PGP two step token against whats on record.
	public function checkTwoStepChallenge($userID,$solution){
		$this->db->where('userID',$userID);
		$this->db->where('twoStepChallenge',$solution);
		$query = $this->db->get('twoStep');

		// Return true if the token matches.
		if($query->num_rows() > 0){
			return TRUE;
		} else {
			// Otherwise return false.
			return FALSE;
		}
	}

	// Add the generated two-step token to a table.
	public function addTwoStepChallenge($userID, $challenge){
		// Remove any old challenges for this user.
		$query = $this->db->get_where('twoStep', array('userID' => $userID));
		if($query->num_rows() > 0){
			$this->db->where('userID',$userID);
			$this->db->delete('twoStep');
		} 

		// Record the challenge for the user.
		$update = array('userID' => $userID,
				'twoStepChallenge' => $challenge);
		if($this->db->insert('twoStep',$update)){
			// Challenge has been recorded.
			return TRUE;
		} else {
			// Unable to record challenge.
			return FALSE;
		}
	}

	// Return the basic info for logging in. 
	public function getLoginInfo($user = FALSE){
		// Select the entries by username.
		$this->db->select('id, userName,userSalt, userHash, userRole, twoStepAuth, items_per_page');

        	//Check what field has been provided, and query database using that field.
        	if (isset($user['userHash'])) {
			$query = $this->db->get_where('users', array('userHash' => $user['userHash']));
		} elseif (isset($user['id'])) {
			$query = $this->db->get_where('users', array('id' => $user['id']));
		} elseif (isset($user['userName'])) { 
			$query = $this->db->get_where('users', array('userName' => $user['userName']));
		} else {
			return FALSE; //No suitable field found.
		}

		// Check if the result exists. 
		if($query->num_rows() > 0){
			// Return the results
			return $query->row_array();
		} else {
			// No results for this username.
			return false;
		}
	}
	

	public function addUser($userData){
		// Insert the array into the users table.
		$query = $this->db->insert('users',$userData);
		if($query){
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// Update the last login time.
	public function setLastLog($username){
		// Update the table.
		$userdata = array('lastLog' => time());
		$this->db->where('username',$username);
		$query = $this->db->update('users',$userdata);
		
		// Test whether the query was successful.
		if($query){
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// Test the submitted username and encrypted password.
	public function checkPass($username,$hash){

		// Select id from the table, with the submitted entries. 
		$this->db->select('id');
		$query = $this->db->get_where('users', array(	'userName' => $username,
								'password' => $hash ));

		// If the result exists, return TRUE.
		if($query->num_rows() > 0){
			return TRUE;
		} else {
			// Otherwise, return FAIL.
			return FALSE;
		}
	}



        //Load the requested user from the database by their the specified field.
        public function get_user($user = FALSE)
        {
		//Select these fields from the database
		$this->db->select('id, userName, userRole, userHash, rating, timeRegistered, twoStepAuth, forcePGPmessage, profileMessage, userSalt, items_per_page');

                //Check what field has been provided, and query database using that field.
                if (isset($user['userHash'])) {
			$query = $this->db->get_where('users', array('userHash' => $user['userHash']));
		} elseif (isset($user['id'])) {
			$query = $this->db->get_where('users', array('id' => $user['id']));
		} elseif (isset($user['userName'])) { 
			$query = $this->db->get_where('users', array('userName' => $user['userName']));
		} else {
			return FALSE; //No suitable field found.
		}

		//If there is a matching row, it is returned to the user
		if($query->num_rows() > 0){
			$results = $query->row_array();
			$results['dispTime'] = $this->general->displayTime($results['timeRegistered']);
	                return $results;
		} else {
			return false;
		}
        }

	//Retrive this users public key.
        public function get_pubKey_by_id($id = FALSE, $fingerprint = NULL)
        {
                //If no user is specified, return nothing.
                if ($id === FALSE) {
                        return NULL;
                }

                //Otherwise, load the public key which corresponds to this ID.
		if($fingerprint == NULL){
			$info = 'key';
		} else {
			$info = 'fingerprint';		
	        }

		$this->db->select($info);                
		$query = $this->db->get_where('publicKeys', array('userId' => $id));

		// If there is a key, return the value.
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			return $result[$info];
		}

		// Otherwise return NULL.
		return NULL;
        }

	// Delete public key by user id.
	public function drop_pubKey_by_id($id = FALSE){
		if($id === FALSE)
			return NULL;

		$this->db->where('userID', $id);
		if($this->db->delete('publicKeys')){
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// Get the profile message of a user id.
	public function get_profileMessage($userID){
		$this->db->where('id',$userID);
		$this->db->select('profileMessage');
		$query = $this->db->get('users');
		// Check if the profile message is set.
		if($query->num_rows() > 0 ){
			$result = $query->row_array();
			// Return the message
			return $result['profileMessage'];
		} else {
			// Otherwise return null.
			return NULL;
		}
	}

}
