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

// Obtener total de tickets del usuario
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE user_id = ?");
$stmt->execute([$user_id]);
$totalTickets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Obtener próximo evento
$stmt = $pdo->prepare("
SELECT e.*, t.ticket_code, t.purchase_date
FROM events e
INNER JOIN tickets t ON e.id = t.event_id
WHERE t.user_id = ? AND e.event_date >= NOW()
ORDER BY e.event_date ASC
LIMIT 1
");
$stmt->execute([$user_id]);
$nextEvent = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener todos los eventos del usuario
$stmt = $pdo->prepare("
SELECT e.*, t.ticket_code, t.purchase_date
FROM events e
INNER JOIN tickets t ON e.id = t.event_id
WHERE t.user_id = ?
ORDER BY e.event_date ASC
");
$stmt->execute([$user_id]);
$userEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para formatear fecha
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
<title>BeatPass - Mi Panel</title>
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
<div class="mb-8">
<h1 class="text-4xl font-bold mb-2">Bienvenido, <span class="gradient-text"><?php echo htmlspecialchars($username); ?></span></h1>
<p class="text-gray-400">Aquí puedes ver todos tus eventos y tickets</p>
</div>

<!-- Estadísticas -->
<div class="grid md:grid-cols-2 gap-6 mb-8">
<!-- Próximo Evento -->
<div class="stat-card rounded-3xl p-6 card-hover">
<div class="flex items-center justify-between mb-4">
<h3 class="text-xl font-semibold text-gray-300">
<i class="fas fa-calendar-check mr-2 text-purple-400"></i>Próximo Evento
</h3>
</div>

<?php if ($nextEvent): ?>
<div class="bg-black/30 rounded-2xl p-4 border border-gray-800">
<div class="flex items-start justify-between mb-3">
<div>
<h4 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($nextEvent['name']); ?></h4>
<p class="text-cyan-400 text-sm">
<i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($nextEvent['location']); ?>
</p>
</div>
<div class="bg-purple-500/20 px-3 py-1 rounded-full text-sm border border-purple-500">
<?php echo htmlspecialchars($nextEvent['genre']); ?>
</div>
</div>

<div class="flex items-center justify-between text-sm text-gray-400 mb-3">
<span>
<i class="fas fa-calendar mr-1"></i><?php echo formatDate($nextEvent['event_date']); ?>
</span>
<span>
<i class="fas fa-clock mr-1"></i><?php echo date('H:i', strtotime($nextEvent['event_date'])); ?> HS
</span>
</div>

<div class="bg-gray-900/50 rounded-xl p-3 border border-gray-700">
<div class="text-xs text-gray-400 mb-1">Código de Ticket</div>
<div class="font-mono text-lg text-purple-400"><?php echo htmlspecialchars($nextEvent['ticket_code']); ?></div>
</div>
</div>
<?php else: ?>
<div class="text-center py-8 text-gray-500">
<i class="fas fa-calendar-times text-4xl mb-3"></i>
<p>No tienes eventos próximos</p>
</div>
<?php endif; ?>
</div>

<!-- Total de Tickets -->
<div class="stat-card rounded-3xl p-6 card-hover">
<div class="flex items-center justify-between mb-4">
<h3 class="text-xl font-semibold text-gray-300">
<i class="fas fa-ticket mr-2 text-cyan-400"></i>Mis Tickets
</h3>
</div>

<div class="text-center py-8">
<div class="text-7xl font-bold gradient-text mb-4"><?php echo $totalTickets; ?></div>
<p class="text-gray-400 text-lg">Eventos Registrados</p>
</div>

<div class="grid grid-cols-2 gap-4 mt-6">
<div class="bg-black/30 rounded-xl p-4 border border-gray-800 text-center">
<div class="text-2xl font-bold text-purple-400">
<?php
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets t INNER JOIN events e ON t.event_id = e.id WHERE t.user_id = ? AND e.event_date >= NOW()");
$stmt->execute([$user_id]);
echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
</div>
<div class="text-sm text-gray-400">Próximos</div>
</div>
<div class="bg-black/30 rounded-xl p-4 border border-gray-800 text-center">
<div class="text-2xl font-bold text-cyan-400">
<?php
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets t INNER JOIN events e ON t.event_id = e.id WHERE t.user_id = ? AND e.event_date < NOW()");
$stmt->execute([$user_id]);
echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
</div>
<div class="text-sm text-gray-400">Pasados</div>
</div>
</div>
</div>
</div>

<!-- Lista de Eventos -->
<div class="mb-8">
<h2 class="text-3xl font-bold mb-6">
<i class="fas fa-list mr-3 text-purple-400"></i>Todos Mis Eventos
</h2>

<?php if (count($userEvents) > 0): ?>
<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
<?php foreach ($userEvents as $event): ?>
<div class="bg-gray-900/50 rounded-3xl overflow-hidden border border-gray-800 card-hover">
<div class="h-32 bg-gradient-to-br from-purple-600 to-cyan-600 flex items-center justify-center">
<i class="fas fa-music text-5xl text-white/80"></i>
</div>

<div class="p-6">
<div class="flex items-start justify-between mb-3">
<div>
<h3 class="text-xl font-bold mb-1"><?php echo htmlspecialchars($event['name']); ?></h3>
<p class="text-sm text-gray-400">
<i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($event['location']); ?>
</p>
</div>
<?php if (strtotime($event['event_date']) < time()): ?>
<span class="bg-gray-700 px-2 py-1 rounded-full text-xs">Pasado</span>
<?php else: ?>
<span class="bg-green-500/20 px-2 py-1 rounded-full text-xs border border-green-500">Próximo</span>
<?php endif; ?>
</div>

<div class="space-y-2 text-sm text-gray-400 mb-4">
<p>
<i class="fas fa-calendar mr-2"></i><?php echo formatDate($event['event_date']); ?>
</p>
<p>
<i class="fas fa-clock mr-2"></i><?php echo date('H:i', strtotime($event['event_date'])); ?> HS
</p>
<p>
<i class="fas fa-guitar mr-2"></i><?php echo htmlspecialchars($event['genre']); ?>
</p>
</div>

<div class="bg-black/50 rounded-xl p-3 mb-4">
<div class="text-xs text-gray-500 mb-1">Código</div>
<div class="font-mono text-sm text-purple-400"><?php echo htmlspecialchars($event['ticket_code']); ?></div>
</div>

<form method="GET" action="ticket.php" target="_blank">
<input type="hidden" name="code" value="<?php echo htmlspecialchars($event['ticket_code']); ?>">
<button type="submit" class="w-full py-2 btn-gradient rounded-xl font-semibold text-sm">
<i class="fas fa-qrcode mr-2"></i>Ver Ticket
</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-16 bg-gray-900/30 rounded-3xl border border-gray-800">
<i class="fas fa-ticket text-6xl text-gray-700 mb-4"></i>
<h3 class="text-2xl font-bold mb-2 text-gray-400">No tienes eventos registrados</h3>
<p class="text-gray-500 mb-6">Comienza a explorar y comprar tickets para tus conciertos favoritos</p>
<a href="explorar.php" class="inline-block px-8 py-3 btn-gradient rounded-full font-semibold">
<i class="fas fa-search mr-2"></i>Explorar Eventos
</a>
</div>
<?php endif; ?>
</div>
</div>
</body>
</html>
