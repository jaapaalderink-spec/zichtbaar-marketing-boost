<?php
declare(strict_types=1);
function validate_contact(array $input): array {
    $data = [];
    foreach (['naam'=>100,'bedrijfsnaam'=>150,'email'=>254,'telefoon'=>40,'bericht'=>2000,'source'=>100] as $key=>$max) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || !preg_match('//u', $value) || strlen($value) > $max) throw new InvalidArgumentException('Controleer de ingevulde velden en de lengte van je bericht.');
        $data[$key] = trim($value);
    }
    if ($data['naam'] === '' || $data['bericht'] === '' || $data['telefoon'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $data['email'].$data['naam'])) throw new InvalidArgumentException('Vul je naam, telefoonnummer, een geldig e-mailadres en je bericht in.');
    return $data;
}
function handle_contact(): never {
    check_csrf();
    $source = is_string($_POST['source'] ?? null) ? $_POST['source'] : '/';
    $return = $source === '/' || in_array($source, array_map(fn($id)=>'/diensten/'.$id, array_keys(defaults())), true) ? $source : '/';
    try {
        if (!empty($_POST['website'])) throw new InvalidArgumentException('Het formulier kon niet worden verwerkt. Probeer opnieuw.');
        $data = validate_contact($_POST);
        $id = $_POST['request_id'] ?? '';
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id) || !isset($_SESSION['contact_ids'][$id])) throw new InvalidArgumentException('Dit formulier is verlopen. Vernieuw de pagina.');
        $exists = db()->prepare('SELECT id FROM messages WHERE id=?'); $exists->execute([$id]);
        if ($exists->fetch()) { flash('success','Je aanvraag is al ontvangen. We nemen contact met je op.'); redirect($return.'#contact'); }
        if (!rate_limit('contact:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),5,3600) || !rate_limit('contact-email:'.strtolower($data['email']),3,3600) || !rate_limit('contact-global',50,3600)) throw new InvalidArgumentException('Je hebt meerdere aanvragen gedaan. Probeer het later opnieuw of bel 085-7605135.');
        db()->prepare('INSERT INTO messages(id,payload,created_at) VALUES(?,?,?)')->execute([$id,json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),time()]);
        require_once __DIR__.'/mail.php';
        $sent = deliver_message($id);
        unset($_SESSION['contact_old']);
        flash('success', $sent ? 'Bedankt! Je aanvraag is ontvangen en per e-mail doorgestuurd. We nemen contact met je op.' : 'Je aanvraag is opgeslagen. De e-mail kon nog niet worden verstuurd. Bel 085-7605135 als je snel contact wilt.');
    } catch (InvalidArgumentException $error) {
        $_SESSION['contact_old'] = array_intersect_key($_POST, array_flip(['naam','bedrijfsnaam','email','telefoon','bericht']));
        flash('error',$error->getMessage());
    }
    redirect($return.'#contact');
}
