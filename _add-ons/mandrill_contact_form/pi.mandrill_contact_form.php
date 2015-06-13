<?php
// Bring in the Mandrill API
require 'Mandrill.php';

class Plugin_mandrill_contact_form extends Plugin {

	public $meta = array(
		'name' 			=> 'Mandrill Contact Form',
		'version' 	=> '0.1',
		'author'		=> 'Jesse Schutt',
		'author_url' => 'http://trinity-studios.com'
	);

	/**
	 * Holds the validation errors
	 * @param  array
	 */
	protected $validation = array();

	/**
	 * Holds your Mandrill API Key
	 * @param  string
	 */
	// protected $mandrill_api_key = 'mandrill-api-key';
	
	/**
	 * Email Form
	 *
	 * Allows you to create an email form, parse the 
	 * posted data, and send it off via Mandrill
	 */
	public function index() {

		// Setup Options
		$options['to'] = $this->fetchParam('to');
		$options['cc'] = $this->fetchParam('cc', '');
		$options['bcc'] = $this->fetchParam('bcc', '');
		$options['from'] = $this->fetchParam('from', '');
		$options['subject'] = $this->fetchParam('subject', 'Email Form', false, false, false);
		$options['class'] = $this->fetchParam('class', '');
		$options['id'] = $this->fetchParam('id', '');

		$required = $this->fetchParam('required');
		$honeypot = $this->fetchParam('honeypot', false, false, true);

    // Set up some default vars.
    $output = '';
    $vars = array(array());

    // If the page has post data process it.
    if (isset($_POST) and ! empty($_POST)) {
      if ( ! $this->validate($_POST, $required)) {
        $vars = array(array('error' => true, 'errors' => $this->validation));
      } elseif ($this->send($_POST, $options)) {
          $vars = array(array('success' => true));
      } else {
          $vars = array(array('error' => true, 'errors' => 'Could not send email'));
      }
    }

		// Display the form on the page.
		$output .= '<form method="post"';

		if( $options['class'] != '') {
		  $output .= ' class="' . $options['class'] . '"';
		}

		if( $options['id'] != '') {
		  $output .= ' id="' . $options['id'] . '"';
		}

		$output .= '>';

		$output .= Parse::tagLoop($this->content, $vars);

		//inject the honeypot if true
		if ($honeypot) {
		  $output .= '<input type="text" name="username" value="" style="display:none" />';
		}

		$output .= '</form>';

		
		return $output;

	}

	/**
	 * Validate the submitted form data
	 *
	 * @param array input
	 * @param string required
	 * @return bool
	 */
	protected function validate($input, $required) {

	  $required = explode('|', str_replace('from', '', $required));

	  // From is always required
	  if ( ! isset($input['from']) or ! filter_var($input['from'], FILTER_VALIDATE_EMAIL)) {
	    $this->validation[0]['error'] = 'From is required';
	  }

	  // Username is never required
	  if (isset($input['username']) && $input['username'] !== '' ) {
	    $this->validation[]['error'] = 'Username is never required';
	  }

	  foreach ($required as $key => $value) {
	    if ($value != '' and $input[$value] == '') {
	      $this->validation[]['error'] = ucfirst($value).' is required';
	    }
	  }

	  return empty($this->validation) ? true : false;
	}

	/**
	 * Send the Email via Mandrill
	 *
	 * @param array $input
	 * @param array $options 
	 * @return bool 
	 */
	protected function send($input, $options) {
				
		// Set up a new class
		// Update "getenv('MANDRILL-KEY')" to $this->mandrill_api_key if you've set it above
		$mandrill = new Mandrill(getenv('MANDRILL-KEY'));

		$message_content = "<p>-------------<br>";
		foreach ($input as $key => $value) {
		    // Checking to see if the value happes to be an array.
		    // For example, on a Multiselect you need to pass
		    // the post as an array to get all the selected options.
		    // If it is an array, it will squish it down to a comma-
		    // separated list
		    if( is_array($value) )
		    {
		        $value = implode(', ', $value);
		    }
		  $message_content .= ucfirst($key).": ".$value."<br>";
		}
		$message_content .= "-------------</p>";
	
		$message = array(
		    'subject' => $options['subject'],
		    'from_email' => $options['from'],
		    'html' => $message_content,
		    'to' => array(
		    					array(
		    						'email' => $options['to']
		    						)
		    					),
		    'headers' => array('Reply-To' => $input['from']),
		    );

		if ($options['cc'] != '')
		{
			array_push($message['to'], ['email' => $options['cc'], 'type' => 'cc']);
		}

		if ($options['bcc'] != '')
		{
			array_push($message['to'], ['email' => $options['bcc'], 'type' => 'bcc']);
		}

		// TODO: Evaluate responses from Mandrill
		return $mandrill->messages->send($message, $async=false, $ip_pool=null, $send_at=null);
	}
}