<?php
/**
 * WordPress User Page
 *
 * Handles authentication, registering, resetting passwords, forgot password,
 * and other user handling.
 *
 * @package WordPress
 */

/** Make sure that the WordPress bootstrap has run before continuing. */
if (!defined('ABSPATH')) {
	//require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php' );
	require_once($_SERVER['DOCUMENT_ROOT'] . dirname(dirname(dirname(dirname($_SERVER['PHP_SELF'])))) . '/wp-load.php' );
} else {
	require_once(ABSPATH . 'wp-load.php');
}

// Redirect to https login if forced to use SSL
if ( force_ssl_admin() && ! is_ssl() ) {
	if ( 0 === strpos($_SERVER['REQUEST_URI'], 'http') ) {
		wp_redirect( set_url_scheme( $_SERVER['REQUEST_URI'], 'https' ) );
		exit();
	} else {
		wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		exit();
	}
}

/**
 * Outputs the header for the login page.
 *
 * @uses do_action() Calls the 'login_head' for outputting HTML in the Log In
 *		header.
 * @uses apply_filters() Calls 'login_headerurl' for the top login link.
 * @uses apply_filters() Calls 'login_headertitle' for the top login title.
 * @uses apply_filters() Calls 'login_message' on the message to display in the
 *		header.
 * @uses $error The error global, which is checked for displaying errors.
 *
 * @param string $title Optional. WordPress Log In Page title to display in
 *		<title/> element.
 * @param string $message Optional. Message to display in header.
 * @param WP_Error $wp_error Optional. WordPress Error Object
 */
function login_header($title = 'Log In', $message = '', $wp_error = '', $extra_ajax_result = '') {
	global $error, $interim_login, $action, $ajax;

	// Don't index any of these forms
	add_action( 'login_head', 'wp_no_robots' );

	if ( wp_is_mobile() )
		add_action( 'login_head', 'wp_login_viewport_meta' );

	if ( empty($wp_error) )
		$wp_error = new WP_Error();

	if ($ajax) {
		if (empty($extra_ajax_result)) {
			$result = array();
		} else {
			$result = $extra_ajax_result;
		}
		$output = array();
	} else {
		?><!DOCTYPE html>
		<!--[if IE 8]>
			<html xmlns="http://www.w3.org/1999/xhtml" class="ie8" <?php language_attributes(); ?>>
		<![endif]-->
		<!--[if !(IE 8) ]><!-->
			<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
		<!--<![endif]-->
		<head>
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php bloginfo('name'); ?> &rsaquo; <?php echo $title; ?></title>
		<?php

		// Remove all stored post data on logging out.
		// This could be added by add_action('login_head'...) like wp_shake_js()
		// but maybe better if it's not removable by plugins
		if ( 'loggedout' == $wp_error->get_error_code() ) {
			?>
			<script>if("sessionStorage" in window){try{for(var key in sessionStorage){if(key.indexOf("wp-autosave-")!=-1){sessionStorage.removeItem(key)}}}catch(e){}};</script>
			<?php
		}

		do_action( 'login_enqueue_scripts' );
		do_action( 'login_head' );

		$classes = array( 'login-action-' . $action, 'wp-core-ui' );
		if ( wp_is_mobile() )
			$classes[] = 'mobile';
		if ( is_rtl() )
			$classes[] = 'rtl';
		$classes[] =' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );
		$classes = apply_filters( 'login_body_class', $classes, $action );
		?>
		<link rel="stylesheet" id="haxor_login"  href="<?php echo get_stylesheet_directory_uri() . '/console-login.css'; ?>" type="text/css" media="all" />
		<script src="<?php echo get_template_directory_uri() . '/smartfragment.js'; ?>" type="text/javascript"></script>
		<script src="<?php echo get_template_directory_uri() . '/console-login.js'; ?>" type="text/javascript"></script>

		</head>
		<body class="login <?php echo esc_attr( implode( ' ', $classes ) ); ?>" onfocus="document.getElementById('in').focus()">
		<?php
		if (have_posts()) {
			the_post();
			the_content();
		}
		?>
		<div id="login-form"><div id="out">
		<?php
	}

	// In case a plugin uses $error rather than the $wp_errors object
	if ( !empty( $error ) ) {
		$wp_error->add('error', $error);
		unset($error);
	}

	if ( $wp_error->get_error_code() ) {
		$errors = '';
		$messages = '';
		foreach ( $wp_error->get_error_codes() as $code ) {
			$severity = $wp_error->get_error_data($code);
			foreach ( $wp_error->get_error_messages($code) as $error ) {
				if ( 'message' == $severity )
					$messages .= '	' . $error . "<br />\n";
				else
					$errors .= '	' . $error . "<br />\n";
			}
		}
		if ( !empty($errors) )
			if ($ajax) {
				$output["err"] = apply_filters('login_errors', $errors);
			} else {
				echo '<div class="login_error">' . apply_filters('login_errors', $errors) . "</div>\n";
			}
		if ( !empty($messages) )
			if ($ajax) {
				$output["msgs"] = apply_filters('login_messages', $messages);
			} else {
				echo '<p class="message">' . apply_filters('login_messages', $messages) . "</p>\n";
			}
	}

	$message = apply_filters('login_message', $message);
	if ( !empty( $message ) )
		if ($ajax) {
			$output["msg"] = $message;
		} else {
			echo $message . "\n";
		}

	if ($ajax) {
		$result['title'] = get_bloginfo('name') . ' &rsaquo; ' . $title;
		$result['output'] = $output;
		ajax_result($result);
		return;
	}
	?>
	</div>
	<div class="dummy-divs"></div>
	<input id="in" type="text" onblur="setTimeout(function(){document.getElementById('in').focus()}, 1000)"/>
	<?php
} // End of login_header()

/**
 * Outputs the footer for the login page.
 *
 * @param string $input_id Which input to auto-focus
 */
function login_footer($input_id = '', $extra_vars = '') {
	global $interim_login, $action, $ajax;
	
	if ($ajax) {
		return;
	}

	$login_prefix = get_option("haxor_login_prefix", get_bloginfo('name') . ' ');
	?>

	</div>

	<script type="text/javascript">
	<?php if ( !empty($input_id) ) : ?>
	try{document.getElementById('<?php echo $input_id; ?>').focus();}catch(e){}
	if(typeof wpOnload=='function')wpOnload();
	<?php endif; ?>
	action = "<?php echo $action; ?>";
	url = "<?php echo site_url( get_self_url(), 'login_post' ); ?>";
	prefix = "<?php echo $login_prefix; ?>";
	<?php if (!empty($extra_vars)) {
		foreach( $extra_vars as $var => $value ) {
			echo $var . ' = "' . $value . '";';
		}
	}?>
        setupAction();
	</script>

	<?php do_action('login_footer'); ?>
	</body>
	</html>
	<?php
}

function wp_shake_js() {
}

/**
 * Handles sending password retrieval email to user.
 *
 * @uses $wpdb WordPress Database object
 *
 * @return bool|WP_Error True: when finish. WP_Error on error
 */
function retrieve_password() {
	global $wpdb, $wp_hasher;

	$errors = new WP_Error();

	if ( empty( $_POST['user_login'] ) ) {
		$errors->add('empty_username', __('<strong>ERROR</strong>: Enter a username or e-mail address.'));
	} else if ( strpos( $_POST['user_login'], '@' ) ) {
		$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
		if ( empty( $user_data ) )
			$errors->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.'));
	} else {
		$login = trim($_POST['user_login']);
		$user_data = get_user_by('login', $login);
	}

	do_action('lostpassword_post');

	if ( $errors->get_error_code() )
		return $errors;

	if ( !$user_data ) {
		$errors->add('invalidcombo', __('<strong>ERROR</strong>: Invalid username or e-mail.'));
		return $errors;
	}

	// redefining user_login ensures we return the right case in the email
	$user_login = $user_data->user_login;
	$user_email = $user_data->user_email;

	do_action('retreive_password', $user_login);  // Misspelled and deprecated
	do_action('retrieve_password', $user_login);

	$allow = apply_filters('allow_password_reset', true, $user_data->ID);

	if ( ! $allow )
		return new WP_Error('no_password_reset', __('Password reset is not allowed for this user'));
	else if ( is_wp_error($allow) )
		return $allow;
	
	// Generate something random for a key...
	$key = wp_generate_password(20, false);
	do_action('retrieve_password_key', $user_login, $key);
	// Now insert the key, hashed, into the DB.
	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . 'wp-includes/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
	}
	$hashed = $wp_hasher->HashPassword( $key );
	$wpdb->update($wpdb->users, array('user_activation_key' => $hashed), array('user_login' => $user_login));
	
	$message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
	$message .= network_home_url( '/' ) . "\r\n\r\n";
	$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
	$message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
	$message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
	$message .= '<' . network_site_url(get_self_url(array('action' => 'rp', 'key' => $key, 'login' => rawurlencode($user_login))), 'login') . ">\r\n";

	if ( is_multisite() )
		$blogname = $GLOBALS['current_site']->site_name;
	else
		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

	$title = sprintf( __('[%s] Password Reset'), $blogname );

	$title = apply_filters('retrieve_password_title', $title);
	$message = apply_filters('retrieve_password_message', $message, $key);

	if ( $message && !wp_mail($user_email, wp_specialchars_decode($title), $message) )
		wp_die( __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function...') );

	return true;
}

function ajax_result( $response ) {
        global $action;
	$response['action'] = $action;
	echo json_encode($response);
}

function get_self_url($extra_params = NULL) {
	$array = parse_url($_SERVER['REQUEST_URI']);
	parse_str($array['query'], $params);
	unset($params['action']);
	unset($params['checkemail']);
	unset($params['loggedout']);
	unset($params['error']);
	unset($params['key']);
	unset($params['login']);
	unset($params['registration']);
	unset($params['ajax']);
	unset($params['reauth']);
	unset($params['_wpnonce']);
	if (!is_null($extra_params)) {
		$params = array_merge($params, $extra_params);
	}
	$path = $array['path'];
	$home_url = home_url();
	$home_url = parse_url($home_url);
	if (isset($home_url['path'])) {
		$home_url = $home_url['path'];
		$path = preg_replace("|^$home_url|i", '', $path);
	}
	return $path . '?' . http_build_query($params);
}

//
// Main
//

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
$ajax = $_REQUEST['ajax'];
$errors = new WP_Error();

if ( isset($_GET['key']) )
	$action = 'resetpass';

// validate action so as to default to the login screen
if ( !in_array( $action, array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'login' ), true ) && false === has_filter( 'login_form_' . $action ) )
	$action = 'login';

nocache_headers();

header('Content-Type: '.get_bloginfo('html_type').'; charset='.get_bloginfo('charset'));

if ( defined( 'RELOCATE' ) && RELOCATE ) { // Move flag is set
	if ( isset( $_SERVER['PATH_INFO'] ) && ($_SERVER['PATH_INFO'] != $_SERVER['PHP_SELF']) )
		$_SERVER['PHP_SELF'] = str_replace( $_SERVER['PATH_INFO'], '', $_SERVER['PHP_SELF'] );

	$url = dirname( set_url_scheme( 'http://' .  $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] ) );
	if ( $url != get_option( 'siteurl' ) )
		update_option( 'siteurl', $url );
}

//Set a cookie now to see if they are supported by the browser.
setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
if ( SITECOOKIEPATH != COOKIEPATH )
	setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);

// allow plugins to override the default actions, and to add extra actions if they want
do_action( 'login_init' );
do_action( 'login_form_' . $action );

$http_post = ('POST' == $_SERVER['REQUEST_METHOD']);
switch ($action) {

case 'postpass' :
	require_once ABSPATH . 'wp-includes/class-phpass.php';
	$hasher = new PasswordHash(8, true);

	// 10 days by default
	$expire = apply_filters( 'post_password_expires', time() + 10 * DAY_IN_SECONDS );
	setcookie( 'wp-postpass_' . COOKIEHASH, $hasher->HashPassword( wp_unslash( $_POST['post_password'] ) ), $expire, COOKIEPATH ); 

	wp_safe_redirect( wp_get_referer() );
	exit();

break;

case 'logout' :
	check_admin_referer('log-out');
	wp_logout();

	$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : site_url(get_self_url(array('loggedout' => true)), 'login');
	if ($ajax) {
		$errors = new WP_Error();
		$errors->add('loggedout', __('You are now logged out.'), 'message');
		if (empty($_REQUEST['redirect_to'] )) {
			$action = 'login';
			login_header(__('Log In'), '', $errors);
		} else {
			login_header(__('Logged Out'), '', $errors, array('redirect' => $redirect_to));
		}
	} else {
		wp_safe_redirect( $redirect_to );
	}
	exit();

break;

case 'lostpassword' :
case 'retrievepassword' :

	if ( $http_post ) {
		$errors = retrieve_password();
		if ( !is_wp_error($errors) ) {
			$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : basename(__FILE__) . '?checkemail=confirm'; //TODO: WHAT THE HELL IS __FILE__ DOING HERE?! FIX THIS!!!
			if ($ajax) {
				$errors = new WP_Error();
				$errors->add('confirm', __('Check your e-mail for the confirmation link.'), 'message');
				$action = 'login';
				login_header(__('Log In'), '', $errors);
			} else {
				wp_safe_redirect( $redirect_to );
			}
			exit();
		}
	}

	if ( isset($_GET['error'])) {
		if ( 'invalidkey' == $_GET['error'] )
			$errors->add( 'invalidkey', __('Sorry, that key does not appear to be valid.'));
		elseif ( 'expiredkey' == $_GET['error'] )
			$errors->add( 'expiredkey', __( 'Sorry, that key has expired. Please try again.' ) );
	}

	$lostpassword_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
	$redirect_to = apply_filters( 'lostpassword_redirect', $lostpassword_redirect );
	
	do_action('lost_password');
	login_header(__('Lost Password'), '<p class="message">' . __('Please enter your username or email address. You will receive a link to create a new password via email.') . '</p>', $errors);

	$user_login = isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';

	if ($ajax) {
            exit();
        }
?>
<noscript>
<form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" method="post">
	<p>
		<label for="user_login" ><?php _e('Username or E-mail:') ?><br />
		<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr($user_login); ?>" size="20" /></label>
	</p>
<?php do_action('lostpassword_form'); ?>
	<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Get New Password'); ?>" /></p>
</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e('Log in') ?></a>
<?php
if ( get_option( 'users_can_register' ) ) :
	$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );
	/**
	 * Filter the registration URL below the login form.
	 *
	 * @since 1.5.0
	 *
	 * @param string $registration_url Registration URL.
	 */
	echo ' | ' . apply_filters( 'register', $registration_url );
endif;
?>
</p>
</noscript>

<?php
login_footer('user_login');
break;

case 'resetpass' :
case 'rp' :
	$user = check_password_reset_key($_GET['key'], $_GET['login']);

	$errors = new WP_Error();

	if ( is_wp_error($user) ) {

		if ($ajax) {
			$action = 'lostpassword';
			if ( $user->get_error_code() === 'expired_key' )
				$errors->add('expiredkey', __('Sorry, that key has expired. Please try again.'));
			else
				$errors->add('invalidkey', __('Sorry, that key does not appear to be valid.'));
			login_header(__('Lost Password'), '<p class="message">' . __('Please enter your username or email address. You will receive a link to create a new password via email.') . '</p>', $errors);
		} else {
			if ( $user->get_error_code() === 'expired_key' )
				wp_redirect( site_url(get_self_url(array('action' => 'lostpassword', 'error' => 'expiredkey'))) );
			else
				wp_redirect( site_url(get_self_url(array('action' => 'lostpassword', 'error' => 'invalidkey'))) );
		}
		exit;
	}

	if ( isset($_POST['pass1']) && $_POST['pass1'] != $_POST['pass2'] )
		$errors->add( 'password_reset_mismatch', __( 'The passwords do not match.' ) );

	do_action( 'validate_password_reset', $errors, $user );

	if ( ( ! $errors->get_error_code() ) && isset( $_POST['pass1'] ) && !empty( $_POST['pass1'] ) ) {
		reset_password($user, $_POST['pass1']);
		if ($ajax) {
			$action = 'login';
		}
		login_header( __( 'Password Reset' ), '<p class="message reset-pass">' . __( 'Your password has been reset.' ) . ( $ajax ? '' : ' <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a>') . '</p>' );
		login_footer();
		exit;
	}

	wp_enqueue_script('utils');
	wp_enqueue_script('user-profile');

	login_header(__('Reset Password'), '<p class="message reset-pass">' . __('Enter your new password below.') . '</p>', $errors, array('user_login' => $_GET['login'], 'key' => $_GET['key']));

	if ($ajax) {
		exit();
	}
?>
<noscript>
<form name="resetpassform" id="resetpassform" action="<?php echo esc_url( site_url( 'wp-login.php?action=resetpass&key=' . urlencode( $_GET['key'] ) . '&login=' . urlencode( $_GET['login'] ), 'login_post' ) ); ?>" method="post" autocomplete="off">
	<input type="hidden" id="user_login" value="<?php echo esc_attr( $_GET['login'] ); ?>" autocomplete="off" />

	<p>
		<label for="pass1"><?php _e('New password') ?><br />
		<input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" /></label>
	</p>
	<p>
		<label for="pass2"><?php _e('Confirm new password') ?><br />
		<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" /></label>
	</p>

	<div id="pass-strength-result" class="hide-if-no-js"><?php _e('Strength indicator'); ?></div>
	<p class="description indicator-hint"><?php _e('Hint: The password should be at least seven characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).'); ?></p>

	<br class="clear" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Reset Password'); ?>" /></p>
</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
<?php
if ( get_option( 'users_can_register' ) ) :
	$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );
	/** This filter is documented in wp-login.php */
	echo ' | ' . apply_filters( 'register', $registration_url );
endif;
?>
</p>
</noscript>

<?php
login_footer('user_pass', array('rp_login' => $_GET['login'], 'rp_key' => $_GET['key']));
break;

case 'register' :
	if ( is_multisite() ) {
		// Multisite uses wp-signup.php
		$redirect_to = apply_filters( 'wp_signup_location', network_site_url('wp-signup.php') );
		if ($ajax) {
			ajax_result(array('redirect' => $redirect_to));
		} else {
			wp_redirect( $reditect_to );
		}
		exit;
	}

	if ( !get_option('users_can_register') ) {
		$redirect_to = site_url(get_self_url(array('registration' => 'disabled')));
		if ($ajax) {
			$errors = new WP_Error();
			$errors->add('registerdisabled', __('User registration is currently not allowed.'));
			$action = '';
			login_header(__('Log In'), '', $errors);
		} else {
			wp_redirect( $redirect_to );
		}
		exit();
	}

	$user_login = '';
	$user_email = '';
	if ( $http_post ) {
		$user_login = $_POST['user_login'];
		$user_email = $_POST['user_email'];
		$errors = register_new_user($user_login, $user_email);
		if ( !is_wp_error($errors) ) {
			$redirect_to = !empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : site_url(get_self_url(array('checkemail' => 'registered')));
			if ($ajax) {
				$errors = new WP_Error();
				$errors->add('registered', __('Registration complete. Please check your e-mail.'), 'message');
				$action = '';
				login_header(__('Log In'), '', $errors);
			} else {
				wp_safe_redirect( $redirect_to );
			}
			exit();
		}
	}

	$redirect_to = apply_filters( 'registration_redirect', !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '' );
	login_header(__('Registration Form'), '<p class="message register">' . __('Register For This Site') . '</p>', $errors);

	if ($ajax) {
		exit();
	}
?>

<noscript>
<form name="registerform" id="registerform" action="<?php echo esc_url( site_url('wp-login.php?action=register', 'login_post') ); ?>" method="post">
	<p>
		<label for="user_login"><?php _e('Username') ?><br />
		<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr(wp_unslash($user_login)); ?>" size="20" /></label>
	</p>
	<p>
		<label for="user_email"><?php _e('E-mail') ?><br />
		<input type="text" name="user_email" id="user_email" class="input" value="<?php echo esc_attr(wp_unslash($user_email)); ?>" size="25" /></label>
	</p>
<?php do_action('register_form'); ?>
	<p id="reg_passmail"><?php _e('A password will be e-mailed to you.') ?></p>
	<br class="clear" />
	<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Register'); ?>" /></p>
</form>

<p id="nav">
<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a> |
<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" title="<?php esc_attr_e( 'Password Lost and Found' ) ?>"><?php _e( 'Lost your password?' ); ?></a>
</p>
</noscript>

<?php
login_footer('user_login');
break;

case 'login' :
default:
	$secure_cookie = '';
	$customize_login = isset( $_REQUEST['customize-login'] );
	if ( $customize_login )
		wp_enqueue_script( 'customize-base' );

	// If the user wants ssl but the session is not ssl, force a secure cookie.
	if ( !empty($_POST['log']) && !force_ssl_admin() ) {
		$user_name = sanitize_user($_POST['log']);
		if ( $user = get_user_by('login', $user_name) ) {
			if ( get_user_option('use_ssl', $user->ID) ) {
				$secure_cookie = true;
				force_ssl_admin(true);
			}
		}
	}

	if ( isset( $_REQUEST['redirect_to'] ) ) {
		$redirect_to = $_REQUEST['redirect_to'];
		// Redirect to https if user wants ssl
		if ( $secure_cookie && false !== strpos($redirect_to, 'wp-admin') )
			$redirect_to = preg_replace('|^http://|', 'https://', $redirect_to);
	} else {
		$redirect_to = admin_url();
	}

	$reauth = empty($_REQUEST['reauth']) ? false : true;

	// If the user was redirected to a secure login form from a non-secure admin page, and secure login is required but secure admin is not, then don't use a secure
	// cookie and redirect back to the referring non-secure admin page. This allows logins to always be POSTed over SSL while allowing the user to choose visiting
	// the admin via http or https.
	if ( !$secure_cookie && is_ssl() && force_ssl_login() && !force_ssl_admin() && ( 0 !== strpos($redirect_to, 'https') ) && ( 0 === strpos($redirect_to, 'http') ) )
		$secure_cookie = false;

	$user = wp_signon( '', $secure_cookie );

	if ( empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
		if ( headers_sent() ) {
			$user = new WP_Error( 'test_cookie', sprintf( __( '<strong>ERROR</strong>: Cookies are blocked due to unexpected output. For help, please see <a href="%1$s">this documentation</a> or try the <a href="%2$s">support forums</a>.' ),
				__( 'http://codex.wordpress.org/Cookies' ), __( 'https://wordpress.org/support/' ) ) );
		} elseif ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[ TEST_COOKIE ] ) ) {
			// If cookies are disabled we can't log in even with a valid user+pass
			$user = new WP_Error( 'test_cookie', sprintf( __( '<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href="%s">enable cookies</a> to use WordPress.' ),
				__( 'http://codex.wordpress.org/Cookies' ) ) );
		}
	}

	$redirect_to = apply_filters('login_redirect', $redirect_to, isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user);

	if ( !is_wp_error($user) && !$reauth ) {
		if ( $interim_login ) {
			$message = '<p class="message">' . __('You have logged in successfully.') . '</p>';
			$interim_login = 'success';
			login_header( '', $message ); ?>
			</div>
			<?php do_action( 'login_footer' ); ?>
			<?php if ( $customize_login ) : ?>
				<script type="text/javascript">setTimeout( function(){ new wp.customize.Messenger({ url: '<?php echo wp_customize_url(); ?>', channel: 'login' }).send('login') }, 1000 );</script>
			<?php endif; ?>
			</body></html>
<?php		exit;
		}

		if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
			// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
			if ( is_multisite() && !get_active_blog_for_user($user->ID) && !is_super_admin( $user->ID ) )
				$redirect_to = user_admin_url();
			elseif ( is_multisite() && !$user->has_cap('read') )
				$redirect_to = get_dashboard_url( $user->ID );
			elseif ( !$user->has_cap('edit_posts') )
				$redirect_to = admin_url('profile.php');
		}
		if (!$ajax) {
			wp_safe_redirect($redirect_to);
		} else {
			ajax_result(array('redirect' => $redirect_to));
		}
		exit();
	}

	$errors = $user;
	// Clear errors if loggedout is set.
	if ( !empty($_GET['loggedout']) || $reauth )
		$errors = new WP_Error();

	if ( $interim_login ) {
		if ( ! $errors->get_error_code() )
			$errors->add('expired', __('Session expired. Please log in again. You will not move away from this page.'), 'message');
	} else {
		// Some parts of this script use the main login form to display a message
		if		( isset($_GET['loggedout']) && true == $_GET['loggedout'] )
			$errors->add('loggedout', __('You are now logged out.'), 'message');
		elseif	( isset($_GET['registration']) && 'disabled' == $_GET['registration'] )
			$errors->add('registerdisabled', __('User registration is currently not allowed.'));
		elseif	( isset($_GET['checkemail']) && 'confirm' == $_GET['checkemail'] )
			$errors->add('confirm', __('Check your e-mail for the confirmation link.'), 'message');
		elseif	( isset($_GET['checkemail']) && 'newpass' == $_GET['checkemail'] )
			$errors->add('newpass', __('Check your e-mail for your new password.'), 'message');
		elseif	( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] )
			$errors->add('registered', __('Registration complete. Please check your e-mail.'), 'message');
		elseif ( strpos( $redirect_to, 'about.php?updated' ) )
			$errors->add('updated', __( '<strong>You have successfully updated WordPress!</strong> Please log back in to experience the awesomeness.' ), 'message' );
	}

	$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );
	
	
	// Clear any stale cookies.
	if ( $reauth )
		wp_clear_auth_cookie();

	login_header(__('Log In'), '', $errors);

	if ($ajax) {
		exit();
	}

	if ( isset($_POST['log']) )
		$user_login = ( 'incorrect_password' == $errors->get_error_code() || 'empty_password' == $errors->get_error_code() ) ? esc_attr(wp_unslash($_POST['log'])) : '';
	$rememberme = ! empty( $_POST['rememberme'] );
?><noscript>

<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
	<p>
		<label for="user_login"><?php _e('Username') ?><br />
		<input type="text" name="log" id="user_login" class="input" value="<?php echo esc_attr($user_login); ?>" size="20" /></label>
	</p>
	<p>
		<label for="user_pass"><?php _e('Password') ?><br />
		<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" /></label>
	</p>
<?php do_action('login_form'); ?>
	<p class="forgetmenot"><label for="rememberme"><input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( $rememberme ); ?> /> <?php esc_attr_e('Remember Me'); ?></label></p>
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Log In'); ?>" />
<?php	if ( $interim_login ) { ?>
		<input type="hidden" name="interim-login" value="1" />
<?php	} else { ?>
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
<?php 	} ?>
<?php   if ( $customize_login ) : ?>
		<input type="hidden" name="customize-login" value="1" />
<?php   endif; ?>
		<input type="hidden" name="testcookie" value="1" />
	</p>
</form>

<?php if ( ! $interim_login ) { ?>
<p id="nav">
<?php if ( ! isset( $_GET['checkemail'] ) || ! in_array( $_GET['checkemail'], array( 'confirm', 'newpass' ) ) ) :
	if ( get_option( 'users_can_register' ) ) :
		$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );
		/** This filter is documented in wp-login.php */
		echo apply_filters( 'register', $registration_url ) . ' | ';
	endif;
	?>
	<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" title="<?php esc_attr_e( 'Password Lost and Found' ); ?>"><?php _e( 'Lost your password?' ); ?></a>
<?php endif; ?>
</p>
<?php } ?></noscript>

<script type="text/javascript">
</script>

<?php

login_footer();
break;
} // end action switch
