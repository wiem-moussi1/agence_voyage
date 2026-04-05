<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
    if (in_array($override, ['PUT', 'DELETE'], true)) {
        $_SERVER['REQUEST_METHOD'] = $override;
    }
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/authController.php';
require_once __DIR__ . '/controllers/voyageController.php';
require_once __DIR__ . '/controllers/reservationController.php';
require_once __DIR__ . '/controllers/avisController.php';
require_once __DIR__ . '/controllers/messageController.php';
require_once __DIR__ . '/controllers/newsletterController.php';
require_once __DIR__ . '/controllers/contactController.php';
require_once __DIR__ . '/controllers/adminVoyageController.php';
require_once __DIR__ . '/controllers/adminReservationController.php';
require_once __DIR__ . '/controllers/adminAvisController.php';
require_once __DIR__ . '/controllers/adminMessageController.php';

$uri = $_GET['uri'] ?? '';

switch ($uri) {

    /* ── Auth ── */
    case 'login':
        authController::login();
        break;
    case 'register':
        authController::register();
        break;
    case 'logout':
        authController::logout();
        break;

    /* ── Voyages (public) ── */
    case 'voyages':
        voyageController::getAll();
        break;

    /* ── Réservations (client) ── */
    case 'reservations':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         reservationController::getUserReservations();
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST')    reservationController::create();
        elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE')  reservationController::cancel();
        break;

    /* ── Avis (client) ── */
    case 'avis':
        if ($_SERVER['REQUEST_METHOD'] === 'POST')        avisController::add();
        elseif ($_SERVER['REQUEST_METHOD'] === 'GET')     avisController::getUserAvis();
        break;

    /* ── Messages (client) ── */
    case 'messages':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         messageController::getConversation();
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST')    messageController::send();
        break;

    /* ── Newsletter (public) ── */
    case 'newsletter':
        if ($_SERVER['REQUEST_METHOD'] === 'POST')        newsletterController::subscribe();
        break;

    /* ── Formulaire de contact (public) ── */
    case 'contact':
        if ($_SERVER['REQUEST_METHOD'] === 'POST')        contactController::send();
        break;

    /* ════════════════ ROUTES ADMIN ════════════════ */

    /* Admin — Voyages */
    case 'admin/voyages':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         adminVoyageController::getAll();
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST')    adminVoyageController::create();
        elseif ($_SERVER['REQUEST_METHOD'] === 'PUT')     adminVoyageController::update();
        elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE')  adminVoyageController::delete();
        break;

    case 'admin/voyages/reservations':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         adminVoyageController::getReservationsByVoyage();
        break;

    /* Admin — Réservations */
    case 'admin/reservations':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         adminReservationController::getAll();
        elseif ($_SERVER['REQUEST_METHOD'] === 'PUT')     adminReservationController::updateStatus();
        elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE')  adminReservationController::cancel();
        break;

    /* Admin — Avis */
    case 'admin/avis':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         adminAvisController::getAll();
        elseif ($_SERVER['REQUEST_METHOD'] === 'PUT')     adminAvisController::validate();
        break;

    /* Admin — Messages */
    case 'admin/messages':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         adminMessageController::getAll();
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST')    adminMessageController::reply();
        break;

    /* Admin — Newsletter */
    case 'admin/newsletter':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         newsletterController::getAll();
        elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE')  newsletterController::delete();
        break;

    /* Admin — Contact */
    case 'admin/contact':
        if ($_SERVER['REQUEST_METHOD'] === 'GET')         contactController::getAll();
        elseif ($_SERVER['REQUEST_METHOD'] === 'PUT')     contactController::updateStatus();
        elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE')  contactController::delete();
        break;

    default:
        echo json_encode(["message" => "API OK"]);
}