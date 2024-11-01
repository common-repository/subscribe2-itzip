<?php
if ( !function_exists('add_action') ) {
	exit();
}

global $s2nonce, $wpdb, $wp_version;

// was anything POSTed?
if ( isset( $_POST['s2_admin']) ) {
	check_admin_referer('subscribe2-options_subscribers' . $s2nonce);
	if ( isset($_POST['reset']) ) {
		$this->reset();
		echo "<div id=\"message\" class=\"updated fade\"><p><strong>$this->options_reset</strong></p></div>";
	} elseif ( isset($_POST['preview']) ) {
		global $user_email, $post;
		$this->preview_email = true;
		if ( 'never' == $this->subscribe2_options['email_freq'] ) {
			$posts = get_posts('numberposts=1');
			$post = $posts[0];
			$this->publish($post, $user_email);
		} else {
			$this->subscribe2_cron($user_email);
		}
		echo "<div id=\"message\" class=\"updated fade\"><p><strong>" . __('Messaggio(i) di anteprima inviato(i) a utenti loggati', 'subscribe2') . "</strong></p></div>";
	} elseif ( isset($_POST['resend']) ) {
		$status = $this->subscribe2_cron('', 'resend');
		if ( $status === false ) {
			echo "<div id=\"message\" class=\"updated fade\"><p><strong>" . __('L\'email di sintesi non conteneva informazione di articoli. Non è stata inviata nessuna email', 'subscribe2') . "</strong></p></div>";
		} else {
			echo "<div id=\"message\" class=\"updated fade\"><p><strong>" . __('Fatto un tentativo di reinvio di email di sintesi', 'subscribe2') . "</strong></p></div>";
		}
	} elseif ( isset($_POST['submit']) ) {
		// BCClimit
		if ( is_numeric($_POST['bcc']) && $_POST['bcc'] >= 0 ) {
			$this->subscribe2_options['bcclimit'] = $_POST['bcc'];
		}
		// admin_email
		$this->subscribe2_options['admin_email'] = $_POST['admin_email'];

		// send as blogname, author or admin?
		if ( is_numeric($_POST['sender']) ) {
			$sender = $_POST['sender'];
		} elseif ($_POST['sender'] == 'author') {
			$sender = 'author';
		} else {
			$sender = 'blogname';
		}
		$this->subscribe2_options['sender'] = $sender;

		// send email for pages, private and password protected posts
		$this->subscribe2_options['stylesheet'] = $_POST['stylesheet'];
		$this->subscribe2_options['pages'] = $_POST['pages'];
		$this->subscribe2_options['password'] = $_POST['password'];
		$this->subscribe2_options['private'] = $_POST['private'];
		$this->subscribe2_options['stickies'] = $_POST['stickies'];
		$this->subscribe2_options['cron_order'] = $_POST['cron_order'];
		$this->subscribe2_options['tracking'] = $_POST['tracking'];

		// send per-post or digest emails
		$email_freq = $_POST['email_freq'];
		$scheduled_time = wp_next_scheduled('s2_digest_cron');
		if ( $email_freq != $this->subscribe2_options['email_freq'] || $_POST['hour'] != date('H', $scheduled_time) ) {
			$this->subscribe2_options['email_freq'] = $email_freq;
			wp_clear_scheduled_hook('s2_digest_cron');
			$scheds = (array)wp_get_schedules();
			$interval = ( isset($scheds[$email_freq]['interval']) ) ? (int) $scheds[$email_freq]['interval'] : 0;
			if ( $interval == 0 ) {
				// if we are on per-post emails remove last_cron entry
				unset($this->subscribe2_options['last_s2cron']);
				unset($this->subscribe2_options['previous_s2cron']);
			} else {
				// if we are using digest schedule the event and prime last_cron as now
				$time = time() + $interval;
				$timestamp = mktime($_POST['hour'], 0, 0, date('m', $time), date('d', $time), date('Y', $time));
				while ($timestamp < time()) {
					// if we are trying to set the time in the past increment it forward
					// by the interval period until it is in the future
					$timestamp += $interval;
				}
				wp_schedule_event($timestamp, $email_freq, 's2_digest_cron');
				if ( !isset($this->subscribe2_options['last_s2cron']) ) {
					$this->subscribe2_options['last_s2cron'] = current_time('mysql');
				}
			}
		}

		// email subject and body templates
		// ensure that are not empty before updating
		if ( !empty($_POST['notification_subject']) ) {
			$this->subscribe2_options['notification_subject'] = $_POST['notification_subject'];
		}
		if ( !empty($_POST['mailtext']) ) {
			$this->subscribe2_options['mailtext'] = $_POST['mailtext'];
		}
		if ( !empty($_POST['confirm_subject']) ) {
			$this->subscribe2_options['confirm_subject'] = $_POST['confirm_subject'];
		}
		if ( !empty($_POST['confirm_email']) ) {
			$this->subscribe2_options['confirm_email'] = $_POST['confirm_email'];
		}
		if ( !empty($_POST['remind_subject']) ) {
			$this->subscribe2_options['remind_subject'] = $_POST['remind_subject'];
		}
		if ( !empty($_POST['remind_email']) ) {
			$this->subscribe2_options['remind_email'] = $_POST['remind_email'];
		}

		// compulsory categories
		if ( !empty($_POST['compulsory']) ) {
			sort($_POST['compulsory']);
			$compulsory_cats = implode(',', $_POST['compulsory']);
		} else {
			$compulsory_cats = '';
		}
		$this->subscribe2_options['compulsory'] = $compulsory_cats;

		// excluded categories
		if ( !empty($_POST['category']) ) {
			sort($_POST['category']);
			$exclude_cats = implode(',', $_POST['category']);
		} else {
			$exclude_cats = '';
		}
		$this->subscribe2_options['exclude'] = $exclude_cats;
		// allow override?
		( isset($_POST['reg_override']) ) ? $override = '1' : $override = '0';
		$this->subscribe2_options['reg_override'] = $override;

		// excluded formats
		if ( !empty($_POST['format']) ) {
			$exclude_formats = implode(',', $_POST['format']);
		} else {
			$exclude_formats = '';
		}
		$this->subscribe2_options['exclude_formats'] = $exclude_formats;

		// default WordPress page where Subscribe2 token is placed
		if ( is_numeric($_POST['page']) && $_POST['page'] >= 0 ) {
			$this->subscribe2_options['s2page'] = $_POST['page'];
		}

		// Number of subscriber per page
		if ( is_numeric($_POST['entries']) && $_POST['entries'] > 0 ) {
			$this->subscribe2_options['entries'] = (int)$_POST['entries'];
		}

		// show meta link?
		( isset($_POST['show_meta']) && $_POST['show_meta'] == '1' ) ? $showmeta = '1' : $showmeta = '0';
		$this->subscribe2_options['show_meta'] = $showmeta;

		// show button?
		( isset($_POST['show_button']) && $_POST['show_button'] == '1' ) ? $showbutton = '1' : $showbutton = '0';
		$this->subscribe2_options['show_button'] = $showbutton;

		// enable AJAX style form
		( isset($_POST['ajax']) && $_POST['ajax'] == '1' ) ? $ajax = '1' : $ajax = '0';
		$this->subscribe2_options['ajax'] = $ajax;

		// show widget in Presentation->Widgets
		( isset($_POST['widget']) && $_POST['widget'] == '1' ) ? $showwidget = '1' : $showwidget = '0';
		$this->subscribe2_options['widget'] = $showwidget;

		// show counterwidget in Presentation->Widgets
		( isset($_POST['counterwidget']) && $_POST['counterwidget'] == '1' ) ? $showcounterwidget = '1' : $showcounterwidget = '0';
		$this->subscribe2_options['counterwidget'] = $showcounterwidget;

		// Subscribe2 over ride postmeta checked by default
		( isset($_POST['s2meta_default']) && $_POST['s2meta_default'] == '1' ) ? $s2meta_default = '1' : $s2meta_default = '0';
		$this->subscribe2_options['s2meta_default'] = $s2meta_default;

		//automatic subscription
		$this->subscribe2_options['autosub'] = $_POST['autosub'];
		$this->subscribe2_options['newreg_override'] = $_POST['newreg_override'];
		$this->subscribe2_options['wpregdef'] = $_POST['wpregdef'];
		$this->subscribe2_options['autoformat'] = $_POST['autoformat'];
		$this->subscribe2_options['show_autosub'] = $_POST['show_autosub'];
		$this->subscribe2_options['autosub_def'] = $_POST['autosub_def'];
		$this->subscribe2_options['comment_subs'] = $_POST['comment_subs'];
		$this->subscribe2_options['comment_def'] = $_POST['comment_def'];
		$this->subscribe2_options['one_click_profile'] = $_POST['one_click_profile'];

		//barred domains
		$this->subscribe2_options['barred'] = $_POST['barred'];

		echo "<div id=\"message\" class=\"updated fade\"><p><strong>$this->options_saved</strong></p></div>";
		update_option('subscribe2_options', $this->subscribe2_options);
	}
}

// send error message if no WordPress page exists
$sql = "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status='publish' LIMIT 1";
$id = $wpdb->get_var($sql);
if ( empty($id) ) {
	echo "<div id=\"page_message\" class=\"error\"><p class=\"s2_error\"><strong>$this->no_page</strong></p></div>";
}

// send error message if sender email address is off-domain
if ( $this->subscribe2_options['sender'] == 'blogname' ) {
	$sender = get_bloginfo('admin_email');
} else {
	$userdata = $this->get_userdata($this->subscribe2_options['sender']);
	$sender = $userdata->user_email;
}
list($user, $domain) = explode('@', $sender, 2);
if ( !strstr($_SERVER['SERVER_NAME'], $domain) && $this->subscribe2_options['sender'] != 'author' ) {
	echo "<div id=\"sender_message\" class=\"error\"><p class=\"s2_error\"><strong>" . __('Sembra che tu stia inviando notifiche da un indirizzo email da un nome di dominio differente dal tuo blog, ciò potrebbe dar luogo a emails non pervenute.', 'subscribe2') . "</strong></p></div>";
}

// show our form
echo "<div class=\"wrap\">";
echo "<div id=\"icon-options-general\" class=\"icon32\"></div>";
echo "<h2>" . __('Impostazioni di Subscribe2', 'subscribe2') . "</h2>\r\n";
echo "<a href=\"http://subscribe2.wordpress.com/\">" . __('Blog dei plugin', 'subscribe2') . "</a> | ";
echo "<a href=\"https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=2387904\">" . __('Fai una donazione via PayPal', 'subscribe2') . "</a>";
echo "<form method=\"post\">\r\n";
if ( function_exists('wp_nonce_field') ) {
	wp_nonce_field('subscribe2-options_subscribers' . $s2nonce);
}
echo "<input type=\"hidden\" name=\"s2_admin\" value=\"options\" />\r\n";
echo "<input type=\"hidden\" id=\"jsbcc\" value=\"" . $this->subscribe2_options['bcclimit'] . "\" />";
echo "<input type=\"hidden\" id=\"jspage\" value=\"" . $this->subscribe2_options['s2page'] . "\" />";
echo "<input type=\"hidden\" id=\"jsentries\" value=\"" . $this->subscribe2_options['entries'] . "\" />";

// settings for outgoing emails
echo "<div class=\"s2_admin\" id=\"s2_notification_settings\">\r\n";
echo "<h2>" . __('Impostazioni delle modifiche', 'subscribe2') . "</h2>\r\n";
echo __('Stabilisci il numero di riceventi per email a (0 per illimitati)', 'subscribe2') . ': ';
echo "<span id=\"s2bcc_1\"><span id=\"s2bcc\" style=\"background-color: #FFFBCC\">" . $this->subscribe2_options['bcclimit'] . "</span> ";
echo "<a href=\"#\" onclick=\"s2_show('bcc'); return false;\">" . __('Modifica', 'subscribe2') . "</a></span>\n";
echo "<span id=\"s2bcc_2\">\r\n";
echo "<input type=\"text\" name=\"bcc\" value=\"" . $this->subscribe2_options['bcclimit'] . "\" size=\"3\" />\r\n";
echo "<a href=\"#\" onclick=\"s2_update('bcc'); return false;\">". __('Aggiorna', 'subscribe2') . "</a>\n";
echo "<a href=\"#\" onclick=\"s2_revert('bcc'); return false;\">". __('Torna indietro', 'subscribe2') . "</a></span>\n";

echo "<br /><br />" . __('Invia notifiche dell\'amministratore per nuove', 'subscribe2') . ': ';
echo "<label><input type=\"radio\" name=\"admin_email\" value=\"subs\"" . checked($this->subscribe2_options['admin_email'], 'subs', false) . " />\r\n";
echo __('Iscrizioni', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"admin_email\" value=\"unsubs\"" . checked($this->subscribe2_options['admin_email'], 'unsubs', false) . " />\r\n";
echo __('Anullamento iscrizioni', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"admin_email\" value=\"both\"" . checked($this->subscribe2_options['admin_email'], 'both', false) . " />\r\n";
echo __('Entrambe', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"admin_email\" value=\"none\"" . checked($this->subscribe2_options['admin_email'], 'none', false) . " />\r\n";
echo __('Nessuna delle due', 'subscribe2') . "</label><br /><br />\r\n";

echo __('Includi stili CSS nelle notifiche HTML', 'subscribe2') . ': ';
echo "<label><input type=\"radio\" name=\"stylesheet\" value=\"yes\"" . checked($this->subscribe2_options['stylesheet'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"stylesheet\" value=\"no\"" . checked($this->subscribe2_options['stylesheet'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";

echo __('Invia emails per le pagine', 'subscribe2') . ': ';
echo "<label><input type=\"radio\" name=\"pages\" value=\"yes\"" . checked($this->subscribe2_options['pages'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"pages\" value=\"no\"" . checked($this->subscribe2_options['pages'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
$s2_post_types = apply_filters('s2_post_types', NULL);
if ( !empty($s2_post_types) ) {
	$types = '';
	echo __('Subscribe2 invierà notifiche email per i seguenti tipi di articoli personalizzati', 'subscribe2') . ': <strong>';
	foreach ($s2_post_types as $type) {
		('' == $types) ? $types = ucwords($type) : $types .= ", " . ucwords($type);
	}
	echo $types . "</strong><br /><br />\r\n";
}
echo __('Invia emails per articoli protetti da password', 'subscribe2') . ': ';
echo "<label><input type=\"radio\" name=\"password\" value=\"yes\"" . checked($this->subscribe2_options['password'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"password\" value=\"no\"" . checked($this->subscribe2_options['password'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('Invia emails per articoli privati', 'subscribe2') . ': ';
echo "<label><input type=\"radio\" name=\"private\" value=\"yes\"" . checked($this->subscribe2_options['private'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"private\" value=\"no\"" . checked($this->subscribe2_options['private'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('Inserisci gli articoli in evidenza in cima a tutte le notifiche associate', 'subscribe2') . ': ';
echo "<label><input type=\"radio\" name=\"stickies\" value=\"yes\"" . checked($this->subscribe2_options['stickies'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"stickies\" value=\"no\"" . checked($this->subscribe2_options['stickies'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('Invia email da', 'subscribe2') . ': ';
echo "<label>\r\n";
$this->admin_dropdown(true);
echo "</label><br /><br />\r\n";
if ( function_exists('wp_schedule_event') ) {
	echo __('Invia email', 'subscribe2') . ": <br /><br />\r\n";
	$this->display_digest_choices();
	echo __('Per le notifiche associate, l\'ordine per data è', 'subscribe2') . ": \r\n";
	echo "<label><input type=\"radio\" name=\"cron_order\" value=\"desc\"" . checked($this->subscribe2_options['cron_order'], 'desc', false) . " /> ";
	echo __('Discendente', 'subscribe2') . "</label>&nbsp;&nbsp;";
	echo "<label><input type=\"radio\" name=\"cron_order\" value=\"asc\"" . checked($this->subscribe2_options['cron_order'], 'asc', false) . " /> ";
	echo __('Ascendente', 'subscribe2') . "</label><br /><br />\r\n";
}
echo __('Associa parametri di tracciamento al permalink:', 'subscribe2') . ": ";
echo "<input type=\"text\" name=\"tracking\" value=\"" . stripslashes($this->subscribe2_options['tracking']) . "\" size=\"50\" /> ";
echo "<br />" . __('cioè. utm_source=subscribe2&amp;utm_medium=email&amp;utm_campaign=postnotify&amp;utm_id={ID}', 'subscribe2') . "<br /><br />\r\n";
echo "</div>\r\n";

// email templates
echo "<div class=\"s2_admin\" id=\"s2_templates\">\r\n";
echo "<h2>" . __('Modelli email', 'subscribe2') . "</h2>\r\n";
echo "<br />";
echo "<table style=\"width: 100%; border-collapse: separate; border-spacing: 5px; *border-collapse: expression('separate', cellSpacing = '5px');\" class=\"editform\">\r\n";
echo "<tr><td style=\"vertical-align: top; height: 350px; min-height: 350px;\">";
echo __('L\'email per in nuovi articoli (non deve essere vuota:)', 'subscribe2') . ":<br />\r\n";
echo __('Riga dell\'oggetto', 'subscribe2') . ": ";
echo "<input type=\"text\" name=\"notification_subject\" value=\"" . stripslashes($this->subscribe2_options['notification_subject']) . "\" size=\"30\" />";
echo "<br />\r\n";
echo "<textarea rows=\"9\" cols=\"60\" name=\"mailtext\">" . stripslashes($this->subscribe2_options['mailtext']) . "</textarea>\r\n";
echo "</td><td style=\"vertical-align: top;\" rowspan=\"3\">";
echo "<p class=\"submit\"><input type=\"submit\" class=\"button-secondary\" name=\"preview\" value=\"" . __('Invia una anteprima dell\'email', 'subscribe2') . "\" /></p>\r\n";
echo "<h3>" . __('Sostituzioni messaggio', 'subscribe2') . "</h3>\r\n";
echo "<dl>";
echo "<dt><b><em style=\"color: red\">" . __('SE LE SEGUENTI PAROLE CHIAVE SI TROVANO ANCHE NEI VOSTRI ARTICOLI SARANNO SOSTITUITE' ,'subscribe2') . "</em></b></dt><dd></dd>\r\n";
echo "<dt><b>{BLOGNAME}</b></dt><dd>" . get_option('blogname') . "</dd>\r\n";
echo "<dt><b>{BLOGLINK}</b></dt><dd>" . get_option('home') . "</dd>\r\n";
echo "<dt><b>{TITLE}</b></dt><dd>" . __("il titolo dell'articolo<br />(<i>solo per l'email per l'articolo</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{POST}</b></dt><dd>" . __("l'estratto o l'articolo intero<br />(<i>basati sulle preferenze dell'iscritto</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{POSTTIME}</b></dt><dd>" . __("l'estratto dell'articolo e l'ora in cui fu pubblicato<br />(<i>solo per le email di sintesi</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{TABLE}</b></dt><dd>" . __("una lista dei titoli degli articoli<br />(<i>solo per le emails di sintesi</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{TABLELINKS}</b></dt><dd>" . __("una lista dei titoli degli articoli seguiti dai links agli articoli<br />(<i>solo per le emails di sintesi</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{PERMALINK}</b></dt><dd>" . __("il permalink dell'articolo<br />(<i>solo per le emails per-articolo</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{TINYLINK}</b></dt><dd>" . __("il permalink dell'articolo dopo la conversione da TinyURL<br />(<i>solo per le emails per-articolo</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{DATE}</b></dt><dd>" . __("la data del post fu creata<br />(<i>solo per le emails per-articolo</i>)", "subscribe2") . "</dd>\r\n";
echo "<dt><b>{TIME}</b></dt><dd>" . __("l'ora del post è stata creata<br />(<i>solo per le emails per-articolo</i>)", "subscribe2") . "</dd>\r\n";
echo "<dt><b>{MYNAME}</b></dt><dd>" . __("il nome dell'admin o dell'autore", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{EMAIL}</b></dt><dd>" . __("l'email dell'admin o dell'autore dell'articolo", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{AUTHORNAME}</b></dt><dd>" . __("il nome dell'autore dell'articolo", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{LINK}</b></dt><dd>" . __("il link generato per la conferma della richiesta<br />(<i>usato solo nel modello dell'email di conferma</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{ACTION}</b></dt><dd>" . __("Azione effettuata da LINK nell'email di conferma<br />(<i>usato solo nel modello dell'email di conferma</i>)", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{CATS}</b></dt><dd>" . __("le categorie assegnate per l'articolo", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{TAGS}</b></dt><dd>" . __("i tags assegnati per l'articolo", 'subscribe2') . "</dd>\r\n";
echo "<dt><b>{COUNT}</b></dt><dd>" . __("il numero di articoli inclusi nell'email di sintesi<br />(<i>solo per le emails di sintesi</i>)", 'subscribe2') . "</dd>\r\n";
echo "</dl></td></tr><tr><td  style=\"vertical-align: top; height: 350px; min-height: 350px;\">";
echo __('Email di conferma per Iscrizione / Cancellazione dell\'iscrizione ', 'subscribe2') . ":<br />\r\n";
echo __('Riga dell\'oggetto', 'subscribe2') . ": ";
echo "<input type=\"text\" name=\"confirm_subject\" value=\"" . stripslashes($this->subscribe2_options['confirm_subject']) . "\" size=\"30\" /><br />\r\n";
echo "<textarea rows=\"9\" cols=\"60\" name=\"confirm_email\">" . stripslashes($this->subscribe2_options['confirm_email']) . "</textarea>\r\n";
echo "</td></tr><tr><td style=\"vertical-align: top; height: 350px; min-height: 350px;\">";
echo __('Iscrizione da confermare per gli iscritti', 'subscribe2') . ":<br />\r\n";
echo __('Linea dell\'oggetto', 'subscribe2') . ": ";
echo "<input type=\"text\" name=\"remind_subject\" value=\"" . stripslashes($this->subscribe2_options['remind_subject']) . "\" size=\"30\" /><br />\r\n";
echo "<textarea rows=\"9\" cols=\"60\" name=\"remind_email\">" . stripslashes($this->subscribe2_options['remind_email']) . "</textarea><br /><br />\r\n";
echo "</td></tr></table><br />\r\n";
echo "</div>\r\n";

// compulsory categories
echo "<div class=\"s2_admin\" id=\"s2_compulsory_categories\">\r\n";
echo "<h2>" . __('Categorie obbligatorie', 'subscribe2') . "</h2>\r\n";
echo "<p>";
echo "<strong><em style=\"color: red\">" . __('Le categorie obbligatorie sarano controllate per impostazione predefinita per gli utenti registrati', 'subscribe2') . "</em></strong><br />\r\n";
echo "</p>";
$this->display_category_form(array(), 1, explode(',', $this->subscribe2_options['compulsory']), 'compulsory');
echo "</div>\r\n";

// excluded categories
echo "<div class=\"s2_admin\" id=\"s2_excluded_categories\">\r\n";
echo "<h2>" . __('Categorie escluse', 'subscribe2') . "</h2>\r\n";
echo "<p>";
echo "<strong><em style=\"color: red\">" . __('Gli articoli assegnati alle categorie escluse non generano notifiche e non sono incluse nelle notifiche di sintesi', 'subscribe2') . "</em></strong><br />\r\n";
echo "</p>";
$this->display_category_form(explode(',', $this->subscribe2_options['exclude']));
echo "<p style=\"text-align: center;\"><label><input type=\"checkbox\" name=\"reg_override\" value=\"1\"" . checked($this->subscribe2_options['reg_override'], '1', false) . " /> ";
echo __('Permettere agli utenti registrati di iscriversi alle categorie escluse?', 'subscribe2') . "</label></p><br />\r\n";
echo "</div>\r\n";

// excluded post formats
$formats = get_theme_support('post-formats');
if ( $formats !== false ) {
	// excluded formats
	echo "<div class=\"s2_admin\" id=\"s2_excluded_formats\">\r\n";
	echo "<h2>" . __('Formati esclusi', 'subscribe2') . "</h2>\r\n";
	echo "<p>";
	echo "<strong><em style=\"color: red\">" . __('Gli articoli assegnati ai formati esclusi non generano notifiche e non sono inclusi nelle notifiche di sintesi', 'subscribe2') . "</em></strong><br />\r\n";
	echo "</p>";
	$this->display_format_form($formats, explode(',', $this->subscribe2_options['exclude_formats']));
	echo "</div>\r\n";
}

// Appearance options
echo "<div class=\"s2_admin\" id=\"s2_appearance_settings\">\r\n";
echo "<h2>" . __('Aspetto', 'subscribe2') . "</h2>\r\n";
echo "<p>";

// WordPress page ID where subscribe2 token is used
echo __('Imposta la pagina preimpostata di Subscribe2 come ID', 'subscribe2') . ': ';
echo "<select name=\"page\">\r\n";
$this->pages_dropdown($this->subscribe2_options['s2page']);
echo "</select>\r\n";

// Number of subscribers per page
echo "<br /><br />" . __('Imposta il numero di iscritti mostrati per pagina', 'subscribe2') . ': ';
echo "<span id=\"s2entries_1\"><span id=\"s2entries\" style=\"background-color: #FFFBCC\">" . $this->subscribe2_options['entries'] . "</span> ";
echo "<a href=\"#\" onclick=\"s2_show('entries'); return false;\">" . __('Modifica', 'subscribe2') . "</a></span>\n";
echo "<span id=\"s2entries_2\">\r\n";
echo "<input type=\"text\" name=\"entries\" value=\"" . $this->subscribe2_options['entries'] . "\" size=\"3\" />\r\n";
echo "<a href=\"#\" onclick=\"s2_update('entries'); return false;\">". __('Aggiorna', 'subscribe2') . "</a>\n";
echo "<a href=\"#\" onclick=\"s2_revert('entries'); return false;\">". __('Ritorna', 'subscribe2') . "</a></span>\n";

// show link to WordPress page in meta
echo "<br /><br /><label><input type=\"checkbox\" name=\"show_meta\" value=\"1\"" . checked($this->subscribe2_options['show_meta'], '1', false) . " /> ";
echo __('Mostrare un link ala tua pagina di iscrizione in "meta"?', 'subscribe2') . "</label><br /><br />\r\n";

// show QuickTag button
echo "<label><input type=\"checkbox\" name=\"show_button\" value=\"1\"" . checked($this->subscribe2_options['show_button'], '1', false) . " /> ";
echo __('Mostrare il pulsante di Subscribe2 nela barra degli strumenti Scrivi?', 'subscribe2') . "</label><br /><br />\r\n";

// enable AJAX style form
echo "<label><input type=\"checkbox\" name=\"ajax\" value=\"1\"" . checked($this->subscribe2_options['ajax'], '1', false) . " /> ";
echo __('Abilitare il form di iscrizione stile AJAX?', 'subscribe2') . "</label><br /><br />\r\n";

// show Widget
echo "<label><input type=\"checkbox\" name=\"widget\" value=\"1\"" . checked($this->subscribe2_options['widget'], '1', false) . " /> ";
echo __('Abilitare il widget di Subscribe2?', 'subscribe2') . "</label><br /><br />\r\n";

// show Counter Widget
echo "<label><input type=\"checkbox\" name=\"counterwidget\" value=\"1\"" . checked($this->subscribe2_options['counterwidget'], '1', false) . " /> ";
echo __('Abilitare il widget contatore di Subscribe2?', 'subscribe2') . "</label><br /><br />\r\n";

// s2_meta checked by default
echo "<label><input type =\"checkbox\" name=\"s2meta_default\" value=\"1\"" . checked($this->subscribe2_options['s2meta_default'], '1', false) . " /> ";
echo __('La disabilitazione delle notifiche email è spuntata per default sulle pagine degli autori?', 'subscribe2') . "</label>\r\n";
echo "</p>";
echo "</div>\r\n";

//Auto Subscription for new registrations
echo "<div class=\"s2_admin\" id=\"s2_autosubscribe_settings\">\r\n";
echo "<h2>" . __('Auto Iscriviti', 'subscribe2') . "</h2>\r\n";
echo "<p>";
echo __('Iscrivi i nuovi utenti che si registrano al tuo blog', 'subscribe2') . ":<br />\r\n";
echo "<label><input type=\"radio\" name=\"autosub\" value=\"yes\"" . checked($this->subscribe2_options['autosub'], 'yes', false) . " /> ";
echo __('Automaticamente', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"autosub\" value=\"wpreg\"" . checked($this->subscribe2_options['autosub'], 'wpreg', false) . " /> ";
echo __('Mostra l\'opzione sul form di Registrazione', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"autosub\" value=\"no\"" . checked($this->subscribe2_options['autosub'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('L\'auto-iscrizione include ogni categoria esclusa', 'subscribe2') . ":<br />\r\n";
echo "<label><input type=\"radio\" name=\"newreg_override\" value=\"yes\"" . checked($this->subscribe2_options['newreg_override'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"newreg_override\" value=\"no\"" . checked($this->subscribe2_options['newreg_override'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('L\'opzione del form di ragistrazione è spuntata per default', 'subscribe2') . ":<br />\r\n";
echo "<label><input type=\"radio\" name=\"wpregdef\" value=\"yes\"" . checked($this->subscribe2_options['wpregdef'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"wpregdef\" value=\"no\"" . checked($this->subscribe2_options['wpregdef'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('Aauto-iscivi gli utenti per ricevere email come', 'subscribe2') . ": <br />\r\n";
echo "<label><input type=\"radio\" name=\"autoformat\" value=\"html\"" . checked($this->subscribe2_options['autoformat'], 'html', false) . " /> ";
echo __('HTML - Full', 'subscribe2') ."</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"autoformat\" value=\"html_excerpt\"" . checked($this->subscribe2_options['autoformat'], 'html_excerpt', false) . " /> ";
echo __('HTML - Estratto', 'subscribe2') ."</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"autoformat\" value=\"post\"" . checked($this->subscribe2_options['autoformat'], 'post', false) . " /> ";
echo __('Testo normale - Full', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"autoformat\" value=\"excerpt\"" . checked($this->subscribe2_options['autoformat'], 'excerpt', false) . " /> ";
echo __('Testo normale - Estratto', 'subscribe2') . "</label><br /><br />";
echo __('Gli utenti registrati non hanno l\'opzione di auto iscriversi a nuove categorie', 'subscribe2') . ": <br />\r\n";
echo "<label><input type=\"radio\" name=\"show_autosub\" value=\"yes\"" . checked($this->subscribe2_options['show_autosub'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"show_autosub\" value=\"no\"" . checked($this->subscribe2_options['show_autosub'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"show_autosub\" value=\"exclude\"" . checked($this->subscribe2_options['show_autosub'], 'exclude', false) . " /> ";
echo __('Le nuove categorie sono immediatamente escluse', 'subscribe2') . "</label><br /><br />";
echo __('L\'opzione per l\'auto iscrizione degli utenti a nuove fcategorie è spuntata per default', 'subscribe2') . ": <br />\r\n";
echo "<label><input type=\"radio\" name=\"autosub_def\" value=\"yes\"" . checked($this->subscribe2_options['autosub_def'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"autosub_def\" value=\"no\"" . checked($this->subscribe2_options['autosub_def'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />";
echo __('Mostra la checkbox per permettere iscrizioni dal form dei commenti', 'subscribe2') . ": <br />\r\n";
echo "<label><input type=\"radio\" name=\"comment_subs\" value=\"before\"" . checked($this->subscribe2_options['comment_subs'], 'before', false) . " /> ";
echo __('Prima del pulsante di invio dei commenti', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"comment_subs\" value=\"after\"" . checked($this->subscribe2_options['comment_subs'], 'after', false) . " /> ";
echo __('Dopo del pulsante di invio dei commenti', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"comment_subs\" value=\"no\"" . checked($this->subscribe2_options['comment_subs'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />";
echo __('Comment form checkbox is checked by default', 'subscrib2') . ": <br />\r\n";
echo "<label><input type=\"radio\" name=\"comment_def\" value=\"yes\"" . checked($this->subscribe2_options['comment_def'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"comment_def\" value=\"no\"" . checked($this->subscribe2_options['comment_def'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label><br /><br />\r\n";
echo __('Mosta la iscrizione con un click sulla pagina del profilo', 'subscribe2') . ":<br />\r\n";
echo "<label><input type=\"radio\" name=\"one_click_profile\" value=\"yes\"" . checked($this->subscribe2_options['one_click_profile'], 'yes', false) . " /> ";
echo __('Si', 'subscribe2') . "</label>&nbsp;&nbsp;";
echo "<label><input type=\"radio\" name=\"one_click_profile\" value=\"no\"" . checked($this->subscribe2_options['one_click_profile'], 'no', false) . " /> ";
echo __('No', 'subscribe2') . "</label>\r\n";
echo "</p></div>\r\n";

//barred domains
echo "<div class=\"s2_admin\" id=\"s2_barred_domains\">\r\n";
echo "<h2>" . __('Dominii blocati', 'subscribe2') . "</h2>\r\n";
echo "<p>";
echo __('Inserisci i domini nella barra dalle iscrizioni pubbliche: <br /> (Usa una nuova linea per ogni nuova linea e ometti il simbolo @, per esempio email.com)', 'subscribe2');
echo "<br />\r\n<textarea style=\"width: 98%;\" rows=\"4\" cols=\"60\" name=\"barred\">" . esc_textarea($this->subscribe2_options['barred']) . "</textarea>";
echo "</p>";
echo "</div>\r\n";

// submit
echo "<p class=\"submit\" style=\"text-align: center\"><input type=\"submit\" class=\"button-primary\" name=\"submit\" value=\"" . __('Invia', 'subscribe2') . "\" /></p>";

// reset
echo "<h2>" . __('Reimposta Default', 'subscribe2') . "</h2>\r\n";
echo "<p>" . __('Usa questo per reimpostare btutte le opzioni al loro valore di default. Questo <strong><em>will non</em></strong> modifica la tua lista di iscritti.', 'subscribe2') . "</p>\r\n";
echo "<p class=\"submit\" style=\"text-align: center\">";
echo "<input type=\"submit\" id=\"deletepost\" name=\"reset\" value=\"" . __('RESET', 'subscribe2') .
"\" />";
echo "</p></form></div>\r\n";

include(ABSPATH . 'wp-admin/admin-footer.php');
// just to be sure
die;
?>