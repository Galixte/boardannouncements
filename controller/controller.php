<?php
/**
*
* @package Board Announcements Extension
* @copyright (c) 2014 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbb\boardannouncements\controller;

class controller
{
	/** @var \phpbb\config\db_text */
	protected $config_text;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/**
	* Constructor
	*
	* @param \phpbb\config\db_text       $config_text    DB text object
	* @param \phpbb\db\driver\driver     $db             Database object
	* @param \phpbb\controller\helper    $helper         Controller helper object
	* @param \phpbb\request\request      $request        Request object
	* @param \phpbb\user                 $user           User object
	* @return \phpbb\boardannouncements\controller\controller
	* @access public
	*/
	public function __construct(\phpbb\config\db_text $config_text, \phpbb\db\driver\driver $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\user $user)
	{
		$this->config_text = $config_text;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->user = $user;
	}

	/**
	* Board Announcements controller accessed from the URL /boardannouncements/{action}
	* (where {action} is a placeholder for a string of text for the $action var below)
	*
	* @param string	$action Action to perform, called by the URL
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	* @access public
	*/
	public function handle($action)
	{
		switch ($action)
		{
			case 'close':

				// Check the link hash to protect against CSRF/XSRF attacks
				if (!check_link_hash($this->request->variable('hash', ''), 'close_boardannouncement'))
				{
					return $this->helper->error($this->user->lang('GENERAL_ERROR'), 200);
				}

				// Set a cookie for guests
				$response = $this->set_board_announcement_cookie();

				// Close the announcement for registered users
				if ($this->user->data['user_id'] != ANONYMOUS)
				{
					$response = $this->update_board_announcement_status();
				}

				// Send a JSON response if an AJAX request was used
				if ($this->request->is_ajax())
				{
					$json_response = new \phpbb\json_response;
					$json_response->send(array(
						'success' => $response,
					));
				}

				// Redirect the user back to their last viewed page (non-AJAX requests)
				$redirect = $this->request->variable('redirect', $this->user->data['session_page']);
				$redirect = reapply_sid($redirect);
				redirect($redirect);

			break;

			default:

				// Display an error message for any invalid access attempts
				return $this->helper->error($this->user->lang('GENERAL_ERROR'), 200);

			break;
		}
	}

	/**
	* Set a cookie to keep an announcement closed
	*
	* @return bool True
	* @access protected
	*/
	protected function set_board_announcement_cookie()
	{
		// Get board announcement data from the DB text object
		$data = $this->config_text->get_array(array(
			'announcement_timestamp',
		));

		// Set a 1 year long cookie
		$this->user->set_cookie('ba_' . $data['announcement_timestamp'], '1', time() + 31536000);

		return true;
	}

	/**
	* Close an announcement for a registered user
	*
	* @return bool True if successful, false otherwise
	* @access protected
	*/
	protected function update_board_announcement_status()
	{
		// Set announcement status to 0 for registered user
		$sql = 'UPDATE ' . USERS_TABLE . '
			SET board_announcements_status = 0
			WHERE user_id = ' . (int) $this->user->data['user_id'] . '
			AND user_type <> ' . USER_IGNORE;
		$this->db->sql_query($sql);

		return (bool) $this->db->sql_affectedrows();
	}
}
