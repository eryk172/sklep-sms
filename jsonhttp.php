<?php

define('IN_SCRIPT', "1");
define('SCRIPT_NAME', "jsonhttp");

require_once "global.php";
require_once SCRIPT_ROOT . "includes/functions_content.php";
require_once SCRIPT_ROOT . "includes/functions_jsonhttp.php";

// Pobranie akcji
$action = $_POST['action'];

// Send no cache headers
header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$data = array();
if ($action == "login") {
    if (is_logged())
        json_output("already_logged_in");

    if (!$_POST['username'] || !$_POST['password']) {
        json_output("no_data", "No niestety, ale bez podania nazwy użytkownika oraz loginu, nie zalogujesz się.", 0);
    }

    $user = $heart->get_user("", $_POST['username'], $_POST['password']);
    if ($user['uid']) {
        $_SESSION['uid'] = $user['uid'];
        update_activity($_SESSION['uid']);
        json_output("logged_in", "Logowanie przebiegło bez większych trudności.", 1);
    }

    json_output("not_logged", "No niestety, ale hasło i/lub nazwa użytkownika są błędne.", 0);
} else if ($action == "logout") {
    if (!is_logged())
        json_output("already_logged_out");

    // Unset all of the session variables.
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    session_destroy();

    json_output("logged_out", "Wylogowywanie przebiegło bez większych trudności.", 1);
} else if ($action == "set_session_language") {
    $_SESSION['language'] = escape_filename($_POST['language']);
    exit;
} else if ($action == "register") {
    if (is_logged()) {
        json_output("logged_in", $lang['not_logged'], 0);
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $passwordr = $_POST['password_repeat'];
    $email = trim($_POST['email']);
    $emailr = trim($_POST['email_repeat']);
    $forename = trim($_POST['forename']);
    $surname = trim($_POST['surname']);
    $as_id = $_POST['as_id'];
    $as_answer = $_POST['as_answer'];
    $sign = $_POST['sign'];

    // Sprawdzanie hashu najwazniejszych danych
    if (!$sign || $sign != md5($as_id . $settings['random_key'])) {
        json_output("wrong_sign", $lang['wrong_sign'], 0);
    }

    // Nazwa użytkownika
    if ($warning = check_for_warnings("username", $username)) {
        $warnings['username'] = $warning;
    }
    $result = $db->query($db->prepare(
        "SELECT uid " .
        "FROM " . TABLE_PREFIX . "users " .
        "WHERE username = '%s'",
        array($username)
    ));
    if ($db->num_rows($result)) {
        $warnings['username'] .= "Podana nazwa użytkownika jest już zajęta.<br />";
    }

    // Hasło
    if ($warning = check_for_warnings("password", $password)) {
        $warnings['password'] = $warning;
    }
    if ($password != $passwordr) {
        $warnings['password_repeat'] .= "Podane hasła różnią się.<br />";
    }

    if ($warning = check_for_warnings("email", $email)) {
        $warnings['email'] = $warning;
    }
    $result = $db->query($db->prepare(
        "SELECT uid " .
        "FROM " . TABLE_PREFIX . "users " .
        "WHERE email = '%s'",
        array($email)
    ));
    if ($db->num_rows($result)) {
        $warnings['email'] .= "Podany e-mail jest już zajęty.<br />";
    }
    if ($email != $emailr) {
        $warnings['email_repeat'] .= "Podane e-maile różnią się.<br />";
    }

    // Pobranie z bazy pytania antyspamowego
    $result = $db->query($db->prepare(
        "SELECT * " .
        "FROM " . TABLE_PREFIX . "antispam_questions " .
        "WHERE id = '%d'",
        array($as_id)
    ));
    $antispam_question = $db->fetch_array_assoc($result);
    if (!in_array(strtolower($as_answer), explode(";", $antispam_question['answers']))) {
        $warnings['as_answer'] .= "Błędna odpowiedź na pytanie antyspamowe.<br />";
    }

    // Pobranie nowego pytania antyspamowego
    $result = $db->query(
        "SELECT * " .
        "FROM " . TABLE_PREFIX . "antispam_questions " .
        "ORDER BY RAND() " .
        "LIMIT 1"
    );
    $antispam_question = $db->fetch_array_assoc($result);
    $data['antispam']['question'] = $antispam_question['question'];
    $data['antispam']['id'] = $antispam_question['id'];
    $data['antispam']['sign'] = md5($antispam_question['id'] . $settings['random_key']);

    // Błędy
    if (!empty($warnings)) {
        foreach ($warnings as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $data['warnings'][$brick] = $warning;
        }
        json_output("warnings", $lang['form_wrong_filled'], 0, $data);
    }

    $salt = get_random_string(8);
    $db->query($db->prepare(
        "INSERT " .
        "INTO " . TABLE_PREFIX . "users (username, password, salt, email, forename, surname, regip) " .
        "VALUES ('%s','%s','%s','%s','%s','%s','%s')",
        array($username, hash_password($password, $salt), $salt, $email, $forename, $surname, $user['ip'])
    ));

    // LOGING
    log_info("Założono nowe konto. ID: " . $db->last_id() . " Nazwa Użytkownika: {$username}, IP: {$user['ip']}");

    json_output("registered", "Konto zostało prawidłowo zarejestrowane. Za chwilę nastąpi automatyczne zalogowanie.", 1, $data);
} else if ($action == "forgotten_password") {
    if (is_logged()) {
        json_output("logged_in", $lang['not_logged'], 0);
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    if ($username || (!$username && !$email)) {
        if ($warning = check_for_warnings("username", $username))
            $warnings['username'] = $warning;
        if ($username) {
            $query = $db->prepare(
                "SELECT uid " .
                "FROM " . TABLE_PREFIX . "users " .
                "WHERE username = '%s'",
                array($username));
            $result = $db->query($query);
            $row = $db->fetch_array_assoc($result);
            if (empty($row)) {
                $warnings['username'] .= "Podana nazwa użytkownika nie jest przypisana do żadnego konta.<br />";
            }
        }
    }

    if (!$username) {
        if ($warning = check_for_warnings("email", $email))
            $warnings['email'] = $warning;
        if ($email) {
            $query = $db->prepare(
                "SELECT uid " .
                "FROM " . TABLE_PREFIX . "users " .
                "WHERE email = '%s'",
                array($email));
            $result = $db->query($query);
            $row = $db->fetch_array_assoc($result);
            if (empty($row)) {
                $warnings['email'] .= "Podany e-mail nie jest przypisany do żadnego konta.<br />";
            }
        }
    }

    // Pobranie danych użytkownika
    $user2 = $heart->get_user($row['uid']);

    // Błędy
    if (!empty($warnings)) {
        foreach ($warnings as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $data['warnings'][$brick] = $warning;
        }
        json_output("warnings", $lang['form_wrong_filled'], 0, $data);
    }

    $key = get_random_string(32);
    $db->query($db->prepare(
        "UPDATE `" . TABLE_PREFIX . "users` " .
        "SET `reset_password_key`='%s' " .
        "WHERE `uid`='%d'",
        array($key, $user2['uid'])
    ));

    $link = $settings['shop_url'] . "/index.php?pid=reset_password&code=" . htmlspecialchars($key);
    eval("\$text = \"" . get_template("emails/forgotten_password") . "\";");
    $ret = send_email($user2['email'], $user2['username'], "Reset Hasła", $text);

    if ($ret == "not_sent") {
        json_output("not_sent", "Wystąpił błąd podczas wysyłania e-maila z linkiem do zresetowania hasła.", 0);
    } else if ($ret == "wrong_email") {
        json_output("wrong_email", "E-mail przypisany do Twojego konta jest błędny. Zgłoś to administratorowi serwisu.", 0);
    } else if ($ret == "sent") {
        log_info("Wysłano e-maila z kodem do zresetowania hasła. Użytkownik: {$user2['username']}({$user2['uid']}) E-mail: {$user2['email']} Dane formularza. Nazwa użytkownika: {$username} E-mail: {$email}");
        $data['username'] = $user2['username'];
        json_output("sent", "E-mail wraz z linkiem do zresetowania hasła został wysłany na Twoją skrzynkę pocztową.", 1, $data);
    }
} else if ($action == "reset_password") {
    if (is_logged()) {
        json_output("logged_in", $lang['not_logged'], 0);
    }

    $uid = $_POST['uid'];
    $sign = $_POST['sign'];
    $pass = $_POST['pass'];
    $passr = $_POST['pass_repeat'];

    // Sprawdzanie hashu najwazniejszych danych
    if (!$sign || $sign != md5($uid . $settings['random_key'])) {
        json_output("wrong_sign", $lang['wrong_sign'], 0);
    }

    if ($warning = check_for_warnings("password", $pass))
        $warnings['pass'] = $warning;
    if ($pass != $passr) {
        $warnings['pass_repeat'] .= "Podane hasła różnią się.<br />";
    }

    // Błędy
    if (!empty($warnings)) {
        foreach ($warnings as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $data['warnings'][$brick] = $warning;
        }
        json_output("warnings", $lang['form_wrong_filled'], 0, $data);
    }

    // Zmień hasło
    $salt = get_random_string(8);

    $db->query($db->prepare(
        "UPDATE " .
        TABLE_PREFIX . "users " .
        "SET password='%s', salt='%s', reset_password_key='' " .
        "WHERE uid='%d'",
        array(hash_password($pass, $salt), $salt, $uid)
    ));

    // LOGING
    log_info("Zresetowano hasło. ID Użytkownika: {$uid}.");

    json_output("password_changed", "Hasło zostało prawidłowo zmienione.", 1);
} else if ($action == "change_password") {
    if (!is_logged()) {
        json_output("logged_in", $lang['not_logged'], 0);
    }

    $oldpass = $_POST['old_pass'];
    $pass = $_POST['pass'];
    $passr = $_POST['pass_repeat'];

    if ($warning = check_for_warnings("password", $pass))
        $warnings['pass'] = $warning;
    if ($pass != $passr) {
        $warnings['pass_repeat'] .= "Podane hasła różnią się.<br />";
    }

    if (hash_password($oldpass, $user['salt']) != $user['password']) {
        $warnings['old_pass'] .= "Stare hasło jest nieprawidłowe.<br />";
    }

    // Błędy
    if (!empty($warnings)) {
        foreach ($warnings as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $data['warnings'][$brick] = $warning;
        }
        json_output("warnings", $lang['form_wrong_filled'], 0, $data);
    }
    // Zmień hasło
    $salt = get_random_string(8);

    $db->query($db->prepare(
        "UPDATE " .
        TABLE_PREFIX . "users " .
        "SET password='%s', salt='%s'" .
        "WHERE uid='%d'",
        array(hash_password($pass, $salt), $salt, $user['uid'])
    ));

    // LOGING
    log_info("Zmieniono hasło. ID użytkownika: {$user['uid']}.");

    json_output("password_changed", "Hasło zostało prawidłowo zmienione.", 1);
} else if ($action == "validate_purchase_form") {
    $service_module = $heart->get_service_module($_POST['service']);
    if (is_null($service_module))
        json_output("wrong_module", $lang['module_is_bad'], 0);

    // Użytkownik nie posiada grupy, która by zezwalała na zakup tej usługi
    if (!$heart->user_can_use_service($user['uid'], $service_module->service))
        json_output("no_permission", $lang['service_no_permission'], 0);

    // Przeprowadzamy walidację danych wprowadzonych w formularzu, a jak zwroci FALSE, to znaczy ze dupa
    if (($return_data = $service_module->validate_purchase_form($_POST)) === FALSE)
        json_output("wrong_module", $lang['module_is_bad'], 0);

    // Przerabiamy ostrzeżenia, aby lepiej wyglądały
    if ($return_data['status'] == "warnings") {
        foreach ($return_data['data']['warnings'] as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $return_data['data']['warnings'][$brick] = $warning;
        }
    } else {
        $data_encoded = base64_encode(json_encode($return_data['purchase_data']));
        $return_data['data'] = array(
            'length' => 8000,
            'data' => $data_encoded,
            'sign' => md5($data_encoded . $settings['random_key'])
        );
    }

    json_output($return_data['status'], $return_data['text'], $return_data['positive'], $return_data['data']);
} else if ($action == "payment_form") {
    // Sprawdzanie hashu danych przesłanych przez formularz
    if (!isset($_POST['sign']) || $_POST['sign'] != md5($_POST['data'] . $settings['random_key'])) {
        output_page($lang['wrong_sign'], "Content-type: text/plain; charset=\"UTF-8\"");
    }

    /** Odczytujemy dane, ich format powinien być taki jak poniżej
     * @param array $data 'service',
     *                        'order'
     *                            ...
     *                        'user',
     *                            'uid',
     *                            'email'
     *                            ...
     *                        'tariff',
     *                        'cost_transfer'
     *                        'no_sms'
     *                        'no_transfer'
     *                        'no_wallet'
     */
    $data = json_decode(base64_decode($_POST['data']), true);

    $service_module = $heart->get_service_module($data['service']);
    if ($service_module === NULL)
        output_page($lang['module_is_bad'], "Content-type: text/plain; charset=\"UTF-8\"");

    // Pobieramy szczegóły zamówienia
    $order_details = $service_module->order_details($data);

    // Pobieramy sposoby płatności
    $payment_methods = "";
    // Sprawdzamy, czy płatność za pomocą SMS jest możliwa
    if ($settings['sms_service'] && isset($data['tariff']) && !$data['no_sms']) {
        $payment_sms = new Payment($settings['sms_service']);
        if (strlen($number = $payment_sms->get_number_by_tariff($data['tariff']))) {
            $tariff['number'] = $number;
            $tariff['cost'] = number_format(get_sms_cost($tariff['number']) * $settings['vat'], 2);
            eval("\$payment_methods .= \"" . get_template("payment_method_sms") . "\";");
        }
    }

    $cost_transfer = number_format($data['cost_transfer'], 2);
    if ($settings['transfer_service'] && isset($data['cost_transfer']) && $data['cost_transfer'] > 1 && !$data['no_transfer']) {
        eval("\$payment_methods .= \"" . get_template("payment_method_transfer") . "\";");
    }
    if (is_logged() && isset($data['cost_transfer']) && !$data['no_wallet']) {
        eval("\$payment_methods .= \"" . get_template("payment_method_wallet") . "\";");
    }

    eval("\$output = \"" . get_template("payment_form") . "\";");
    output_page($output, "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "validate_payment_form") {
    // Sprawdzanie hashu danych przesłanych przez formularz
    if (!isset($_POST['purchase_sign']) || $_POST['purchase_sign'] != md5($_POST['purchase_data'] . $settings['random_key']))
        json_output("wrong_sign", $lang['wrong_sign'], 0);

    // Te same dane, co w "payment_form"
    $payment_data = json_decode(base64_decode($_POST['purchase_data']), true);
    $payment_data['method'] = $_POST['method'];
    $payment_data['sms_code'] = $_POST['sms_code'];

    $return_payment = validate_payment($payment_data);
    json_output($return_payment['status'], $return_payment['text'], $return_payment['positive'], $return_payment['data']);
} else if ($action == "refresh_bricks") {
    if (isset($_POST['bricks']))
        $bricks = explode(";", $_POST['bricks']);

    foreach ($bricks as $brick) {
        $array = get_content($brick, false, true);
        $data[$brick]['class'] = $array['class'];
        $data[$brick]['content'] = $array['content'];
    }

    output_page(json_encode($data), "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "get_service_long_description") {
    $output = "";
    if (($service_module = $heart->get_service_module($_POST['service'])) !== NULL)
        $output = $service_module->get_full_description();

    output_page($output, "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "get_purchase_info") {
    output_page(purchase_info(array(
        'purchase_id' => $_POST['purchase_id'],
        'action' => "web"
    )), "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "form_edit_user_service") {
    if (!is_logged())
        output_page($lang['service_cant_be_modified']);

    // Użytkownik nie może edytować usługi
    if (!$settings['user_edit_service'])
        output_page($lang['not_logged']);

    $result = $db->query($db->prepare(
        "SELECT * FROM `" . TABLE_PREFIX . "players_services` " .
        "WHERE `id` = '%d'",
        array($_POST['id'])
    ));

    // Brak takiej usługi w bazie
    if (!$db->num_rows($result))
        output_page($lang['dont_play_games']);

    $player_service = $db->fetch_array_assoc($result);
    // Dany użytkownik nie jest właścicielem usługi o danym id
    if ($player_service['uid'] != $user['uid'])
        output_page($lang['dont_play_games']);

    if (($service_module = $heart->get_service_module($player_service['service'])) === NULL)
        output_page($lang['service_cant_be_modified']);

    if (($output = $service_module->get_form("user_edit_user_service", $player_service)) === FALSE)
        output_page($lang['service_cant_be_modified']);

    eval("\$buttons = \"" . get_template("services/my_services_savencancel") . "\";");

    output_page($buttons . $output);
} else if ($action == "get_user_service_brick") {
    if (!is_logged())
        output_page($lang['not_logged']);

    // Sprawdzamy, czy usluga ktora chcemy edytowac jest w bazie
    $result = $db->query($db->prepare(
        "SELECT * FROM `" . TABLE_PREFIX . "players_services` " .
        "WHERE `id` = '%d'",
        array($_POST['id'])
    ));

    // Brak takiej usługi w bazie
    if (!$db->num_rows($result))
        output_page($lang['dont_play_games']);

    $player_service = $db->fetch_array_assoc($result);
    // Dany użytkownik nie jest właścicielem usługi o danym id
    if ($player_service['uid'] != $user['uid'])
        output_page($lang['dont_play_games']);

    if (($service_module = $heart->get_service_module($player_service['service'])) === NULL)
        output_page($lang['service_cant_be_modified']);

    if (!class_has_interface($service_module, "IServiceUserEdit"))
        output_page($lang['service_cant_be_modified']);

    $button_edit = create_dom_element("img", "", array(
        'class' => "edit_row",
        'src' => "images/pencil.png",
        'title' => "Edytuj",
        'style' => array(
            'height' => '24px'
        )
    ));

    output_page($service_module->my_service_info($player_service, $button_edit));
} else if ($action == "edit_user_service") {
    if (!is_logged())
        json_output("not_logged", $lang['not_logged'], 0);

    $result = $db->query($db->prepare(
        "SELECT * FROM `" . TABLE_PREFIX . "players_services` " .
        "WHERE `id` = '%d'",
        array($_POST['id'])
    ));

    // Brak takiej usługi w bazie
    if (!$db->num_rows($result))
        json_output("dont_play_games", $lang['dont_play_games'], 0);

    $user_service = $db->fetch_array_assoc($result);
    // Dany użytkownik nie jest właścicielem usługi o danym id
    if ($user_service['uid'] != $user['uid'])
        json_output("dont_play_games", $lang['dont_play_games'], 0);

    if (($service_module = $heart->get_service_module($user_service['service'])) === NULL)
        json_output("wrong_module", $lang['module_is_bad'], 0);

    // Wykonujemy metode edycji usługi gracza na module, który ją obsługuje
    if (!class_has_interface($service_module, "IServiceUserEdit"))
        json_output("service_cant_be_modified", $lang['service_cant_be_modified'], 0);

    $return_data = $service_module->user_edit_user_service($_POST, $user_service);

    // Przerabiamy ostrzeżenia, aby lepiej wyglądały
    if ($return_data['status'] == "warnings") {
        foreach ($return_data['data']['warnings'] as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $return_data['data']['warnings'][$brick] = $warning;
        }
    }

    json_output($return_data['status'], $return_data['text'], $return_data['positive'], $return_data['data']);
} else if ($action == "form_take_over_service") {
    if (($service_module = $heart->get_service_module($_POST['service'])) === NULL || !class_has_interface($service_module, "IServiceTakeOver"))
        output_page($lang['module_is_bad'], "Content-type: text/plain; charset=\"UTF-8\"");

    output_page($service_module->form_take_over_service($_POST['service']), "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "take_over_service") {
    if (($service_module = $heart->get_service_module($_POST['service'])) === NULL || !class_has_interface($service_module, "IServiceTakeOver"))
        output_page($lang['module_is_bad'], "Content-type: text/plain; charset=\"UTF-8\"");

    $return_data = $service_module->take_over_service($_POST);

    // Przerabiamy ostrzeżenia, aby lepiej wyglądały
    if ($return_data['status'] == "warnings") {
        foreach ($return_data['data']['warnings'] as $brick => $warning) {
            eval("\$warning = \"" . get_template("form_warning") . "\";");
            $return_data['data']['warnings'][$brick] = $warning;
        }
    }

    json_output($return_data['status'], $return_data['text'], $return_data['positive'], $return_data['data']);
} else if ($action == "execute_service_action") {
    if (($service_module = $heart->get_service_module($_POST['service'])) === NULL || !class_has_interface($service_module, "IServiceExecuteAction"))
        output_page($lang['module_is_bad'], "Content-type: text/plain; charset=\"UTF-8\"");

    output_page($service_module->execute_action($_POST['service_action'], $_POST), "Content-type: text/plain; charset=\"UTF-8\"");
} else if ($action == "get_template") {
    $template = $_POST['template'];
    // Zabezpieczanie wszystkich wartości post
    foreach ($_POST as $key => $value) {
        $_POST[$key] = htmlspecialchars($value);
    }

    if ($template == "register_registered") {
        $username = htmlspecialchars($_POST['username']);
        $email = htmlspecialchars($_POST['email']);
    } else if ($template == "forgotten_password_sent") {
        $username = htmlspecialchars($_POST['username']);
    }

    if (!isset($data['template']))
        eval("\$data['template'] = \"" . get_template("jsonhttp/" . $template) . "\";");

    output_page(json_encode($data), "Content-type: text/plain; charset=\"UTF-8\"");
}

json_output("script_error", "Błąd programistyczny.", 0);