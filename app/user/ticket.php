<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../auth.php');
    exit();
}

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'beatpass');
define('DB_USER', 'beatpass');
define('DB_PASS', 'beatpass');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($code === '') {
    http_response_code(400);
    echo 'Código de ticket inválido.';
    exit();
}

$stmt = $pdo->prepare('SELECT t.id AS ticket_id, t.ticket_code, t.purchase_date, t.status,
                              e.name, e.location, e.event_date, e.genre, e.price
                       FROM tickets t
                       INNER JOIN events e ON e.id = t.event_id
                       WHERE t.user_id = ? AND t.ticket_code = ?
                       LIMIT 1');
$stmt->execute([$user_id, $code]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    http_response_code(404);
    echo 'Ticket no encontrado.';
    exit();
}

function formatDateLong($date)
{
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $d = new DateTime($date);
    return $d->format('d') . ' de ' . $months[(int)$d->format('m') - 1] . ' de ' . $d->format('Y');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comprobante de Ticket - BeatPass</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');

* {
    font-family: 'Poppins', sans-serif;
}

.gradient-text {
    background: linear-gradient(135deg, #a855f7 0%, #06b6d4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.ticket-bg {
    background: radial-gradient(circle at top left, rgba(168, 85, 247, 0.6), transparent 55%),
                radial-gradient(circle at bottom right, rgba(6, 182, 212, 0.6), transparent 55%),
                #020617;
}

@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .no-print {
        display: none !important;
    }
}
</style>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-2xl ticket-bg rounded-3xl border border-purple-500/40 shadow-2xl overflow-hidden">
    <div class="flex items-center justify-between px-8 py-6 border-b border-purple-500/30 bg-black/40">
        <div class="flex items-center space-x-3">
            <i class="fas fa-music text-3xl gradient-text"></i>
            <div>
                <div class="text-xl font-bold gradient-text">BeatPass</div>
                <div class="text-xs text-gray-400">Comprobante de ticket electrónico</div>
            </div>
        </div>
        <div class="text-right text-xs text-gray-400">
            <div>ID Ticket: <span class="font-mono text-purple-300"><?php echo (int)$ticket['ticket_id']; ?></span></div>
            <div>Emitido: <?php echo date('d/m/Y H:i'); ?></div>
        </div>
    </div>

    <div class="px-8 py-6 grid md:grid-cols-3 gap-6 bg-black/30">
        <div class="md:col-span-2 space-y-3">
            <div class="text-xs uppercase text-gray-400 tracking-wide">Evento</div>
            <div class="text-2xl font-bold"><?php echo htmlspecialchars($ticket['name']); ?></div>
            <div class="text-sm text-gray-300 flex items-center">
                <i class="fas fa-map-marker-alt mr-2 text-cyan-400"></i>
                <?php echo htmlspecialchars($ticket['location']); ?>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-gray-300 mt-2">
                <span class="flex items-center">
                    <i class="fas fa-calendar mr-2 text-purple-400"></i>
                    <?php echo formatDateLong($ticket['event_date']); ?>
                </span>
                <span class="flex items-center">
                    <i class="fas fa-clock mr-2 text-cyan-400"></i>
                    <?php echo date('H:i', strtotime($ticket['event_date'])); ?> HS
                </span>
                <span class="flex items-center">
                    <i class="fas fa-guitar mr-2 text-pink-400"></i>
                    <?php echo htmlspecialchars($ticket['genre']); ?>
                </span>
            </div>
        </div>
        <div class="md:border-l md:border-purple-500/30 md:pl-6 flex flex-col justify-center gap-3">
            <div class="text-xs uppercase text-gray-400 tracking-wide">Titular del ticket</div>
            <div class="text-lg font-semibold"><?php echo htmlspecialchars($username); ?></div>
            <div class="text-xs text-gray-400">Usuario registrado en BeatPass</div>
        </div>
    </div>

    <div class="px-8 py-6 grid md:grid-cols-2 gap-6 bg-black/40 border-t border-b border-purple-500/20">
        <div class="space-y-2">
            <div class="text-xs uppercase text-gray-400 tracking-wide">Detalles del ticket</div>
            <div class="bg-black/40 border border-gray-700 rounded-2xl px-4 py-3">
                <div class="text-xs text-gray-400">Código de ticket</div>
                <div class="font-mono text-base text-purple-300 mt-1"><?php echo htmlspecialchars($ticket['ticket_code']); ?></div>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
                <div class="bg-black/40 border border-gray-700 rounded-2xl px-4 py-3">
                    <div class="text-xs text-gray-400">Estado</div>
                    <div class="mt-1">
                        <?php if ($ticket['status'] === 'used'): ?>
                            <span class="px-2 py-1 rounded-full text-xs border border-green-500 text-green-300 flex items-center gap-1">
                                <i class="fas fa-check"></i> Usado
                            </span>
                        <?php elseif ($ticket['status'] === 'cancelled'): ?>
                            <span class="px-2 py-1 rounded-full text-xs border border-red-500 text-red-300 flex items-center gap-1">
                                <i class="fas fa-times"></i> Cancelado
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded-full text-xs border border-cyan-500 text-cyan-300 flex items-center gap-1">
                                <i class="fas fa-ticket-alt"></i> Activo
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-black/40 border border-gray-700 rounded-2xl px-4 py-3">
                    <div class="text-xs text-gray-400">Fecha de compra</div>
                    <div class="mt-1 text-sm text-gray-200"><?php echo date('d/m/Y H:i', strtotime($ticket['purchase_date'])); ?></div>
                </div>
            </div>
        </div>
        <div class="space-y-2">
            <div class="text-xs uppercase text-gray-400 tracking-wide">Resumen de pago</div>
            <div class="bg-black/40 border border-gray-700 rounded-2xl px-4 py-3 text-sm flex items-center justify-between">
                <span class="text-gray-300 flex items-center">
                    <i class="fas fa-dollar-sign mr-2 text-green-400"></i>Importe total
                </span>
                <span class="font-semibold text-green-300"><?php echo number_format($ticket['price'], 2, ',', '.'); ?> ARS</span>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                Este comprobante certifica la compra de un ticket electrónico para el evento indicado.
                Presenta el código en la entrada del evento o en los controles donde se solicite.
            </p>
        </div>
    </div>

    <div class="px-8 py-4 flex items-center justify-between bg-black/40">
        <div class="text-[10px] text-gray-500">
            <p>BeatPass · Plataforma de tickets electrónicos para eventos musicales.</p>
            <p>Este documento es válido sin firma ni sello. Verifica siempre tus datos antes del evento.</p>
        </div>
        <div class="no-print flex flex-col items-end gap-2">
            <button onclick="window.print()" class="px-4 py-2 btn-gradient rounded-full text-xs font-semibold">
                <i class="fas fa-file-pdf mr-2"></i>Imprimir / Guardar PDF
            </button>
            <a href="index.php" class="text-xs text-gray-400 hover:text-white">
                <i class="fas fa-arrow-left mr-1"></i>Volver al panel
            </a>
        </div>
    </div>
</div>
</body>
</html>
