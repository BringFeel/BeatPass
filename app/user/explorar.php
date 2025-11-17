<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth.php");
    exit();
}

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'beatpass');
define('DB_USER', 'beatpass');
define('DB_PASS', 'beatpass');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$success = '';
$error = '';

// Comprar ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_ticket'])) {
    $event_id = (int)$_POST['event_id'];

    try {
        $pdo->beginTransaction();

        // Verificar evento y disponibilidad
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND available_tickets > 0");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            throw new Exception('El evento no está disponible o se agotaron los tickets');
        }

        // Generar código de ticket simple
        $ticket_code = 'BP-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $event_id;

        // Insertar ticket
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, event_id, ticket_code, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$user_id, $event_id, $ticket_code]);

        // Actualizar disponibilidad
        $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - 1 WHERE id = ?");
        $stmt->execute([$event_id]);

        $pdo->commit();
        $success = '¡Ticket comprado con éxito! Ya puedes verlo en tu panel de eventos.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'No se pudo completar la compra: ' . $e->getMessage();
    }
}

// Obtener eventos con tickets disponibles (sin filtrar por fecha)
$stmt = $pdo->prepare("SELECT * FROM events WHERE available_tickets > 0 ORDER BY event_date ASC");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDate($date) {
    $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $dateObj = new DateTime($date);
    return $dateObj->format('d') . ' ' . $months[(int)$dateObj->format('m') - 1] . ' ' . $dateObj->format('Y');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BeatPass - Explorar Eventos</title>
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

.btn-gradient {
    background: linear-gradient(135deg, #a855f7 0%, #06b6d4 100%);
    transition: all 0.3s ease;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(168, 85, 247, 0.4);
}

.card-hover {
    transition: all 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(168, 85, 247, 0.3);
}

.stat-card {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    border: 2px solid rgba(168, 85, 247, 0.3);
}
</style>
</head>
<body class="bg-black text-white min-h-screen">
<!-- Header -->
<header class="bg-gray-900/50 border-b border-gray-800 sticky top-0 z-50 backdrop-blur-md">
<nav class="container mx-auto px-6 py-4">
<div class="flex items-center justify-between">
<div class="flex items-center space-x-2">
<i class="fas fa-music text-3xl gradient-text"></i>
<span class="text-2xl font-bold gradient-text">BeatPass</span>
</div>

<div class="flex items-center space-x-6">
<a href="index.php" class="text-gray-400 hover:text-white transition">
<i class="fas fa-home mr-2"></i>Mi Panel
</a>
<span class="text-gray-400">
<i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($username); ?>
</span>
<a href="logout.php" class="px-4 py-2 bg-red-500/20 border border-red-500 rounded-full hover:bg-red-500/30 transition">
<i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
</a>
</div>
</div>
</nav>
</header>

<div class="container mx-auto px-6 py-8">
<!-- Título -->
<div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
<div>
<h1 class="text-4xl font-bold mb-2">Explorar <span class="gradient-text">Eventos</span></h1>
<p class="text-gray-400">Descubre nuevos shows y compra tus tickets al instante</p>
</div>
<div class="flex items-center gap-3">
<a href="index.php" class="px-4 py-2 bg-gray-900/60 border border-gray-700 rounded-full text-sm hover:bg-gray-800 transition">
<i class="fas fa-arrow-left mr-2"></i>Volver a mis eventos
</a>
</div>
</div>

<!-- Mensajes -->
<?php if ($error): ?>
<div class="mb-6 bg-red-500/20 border border-red-500 text-red-300 px-4 py-3 rounded-2xl">
<i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="mb-6 bg-green-500/20 border border-green-500 text-green-300 px-4 py-3 rounded-2xl">
<i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<!-- Resumen -->
<div class="grid md:grid-cols-3 gap-6 mb-8">
<div class="stat-card rounded-3xl p-6">
<h3 class="text-lg font-semibold mb-2 flex items-center">
<i class="fas fa-music mr-2 text-purple-400"></i>Eventos disponibles
</h3>
<p class="text-4xl font-bold gradient-text"><?php echo count($events); ?></p>
<p class="text-gray-400 text-sm mt-2">Eventos próximos con tickets aún disponibles</p>
</div>
<div class="bg-gray-900/60 rounded-3xl p-6 border border-gray-800">
<h3 class="text-lg font-semibold mb-2 flex items-center">
<i class="fas fa-ticket-alt mr-2 text-cyan-400"></i>Cómo funciona
</h3>
<ul class="space-y-1 text-sm text-gray-400">
<li>1. Elige tu evento favorito</li>
<li>2. Presiona "Comprar ticket"</li>
<li>3. Tu ticket aparecerá en "Todos mis eventos"</li>
</ul>
</div>
<div class="bg-gray-900/60 rounded-3xl p-6 border border-gray-800 flex items-center">
<p class="text-gray-400 text-sm">
<i class="fas fa-info-circle mr-2 text-purple-400"></i>
Los tickets son digitales y se almacenan en tu cuenta. Podrás ver el código desde tu panel.
</p>
</div>
</div>

<!-- Lista de eventos -->
<h2 class="text-2xl font-bold mb-4 flex items-center">
<i class="fas fa-search mr-3 text-purple-400"></i>Eventos para vos
</h2>

<?php if (count($events) > 0): ?>
<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
<?php foreach ($events as $event): ?>
<div class="bg-gray-900/50 rounded-3xl overflow-hidden border border-gray-800 card-hover flex flex-col">
<div class="h-32 bg-gradient-to-br from-purple-600 to-cyan-600 flex items-center justify-center">
<i class="fas fa-music text-5xl text-white/80"></i>
</div>

<div class="p-6 flex-1 flex flex-col">
<div class="flex items-start justify-between mb-3">
<div>
<h3 class="text-xl font-bold mb-1"><?php echo htmlspecialchars($event['name']); ?></h3>
<p class="text-sm text-gray-400">
<i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($event['location']); ?>
</p>
</div>
<span class="bg-purple-500/20 px-2 py-1 rounded-full text-xs border border-purple-500"><?php echo htmlspecialchars($event['genre']); ?></span>
</div>

<div class="space-y-2 text-sm text-gray-400 mb-4">
<p>
<i class="fas fa-calendar mr-2"></i><?php echo formatDate($event['event_date']); ?>
</p>
<p>
<i class="fas fa-clock mr-2"></i><?php echo date('H:i', strtotime($event['event_date'])); ?> HS
</p>
<p>
<i class="fas fa-dollar-sign mr-2"></i><?php echo number_format($event['price'], 2, ',', '.'); ?> ARS
</p>
<p>
<i class="fas fa-users mr-2"></i><?php echo (int)$event['available_tickets']; ?> tickets disponibles
</p>
</div>

<form method="POST" class="mt-auto">
<input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>">
<button type="submit" name="buy_ticket" class="w-full py-2 btn-gradient rounded-xl font-semibold text-sm">
<i class="fas fa-ticket-alt mr-2"></i>Comprar ticket
</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-16 bg-gray-900/30 rounded-3xl border border-gray-800">
<i class="fas fa-calendar-times text-6xl text-gray-700 mb-4"></i>
<h3 class="text-2xl font-bold mb-2 text-gray-400">No hay eventos disponibles por ahora</h3>
<p class="text-gray-500">Vuelve más tarde para descubrir nuevos conciertos y festivales.</p>
</div>
<?php endif; ?>
</div>
</body>
</html>
