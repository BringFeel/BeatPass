<?php
session_start();

// Verificar sesión de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// Crear tabla para usuarios bloqueados si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_users (
        user_id INT NOT NULL PRIMARY KEY,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_blocked_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (PDOException $e) {
    // Si falla la creación, continuamos pero sin funcionalidad de bloqueo
}

$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'];

$error = '';
$success = '';

// Crear evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $event_date = $_POST['event_date'];
    $genre = trim($_POST['genre']);
    $available_tickets = (int)$_POST['available_tickets'];
    $price = (float)$_POST['price'];
    $image_url = trim($_POST['image_url']);

    if ($name === '' || $location === '' || $event_date === '' || $genre === '' || $available_tickets < 0 || $price < 0) {
        $error = 'Todos los campos obligatorios deben completarse correctamente.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO events (name, description, location, event_date, genre, available_tickets, price, image_url, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $description,
                $location,
                $event_date,
                $genre,
                $available_tickets,
                $price,
                $image_url !== '' ? $image_url : null,
                $admin_id
            ]);
            $success = 'Evento creado correctamente.';
        } catch (PDOException $e) {
            $error = 'No se pudo crear el evento: ' . $e->getMessage();
        }
    }
}

// Eliminar evento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $event_id = (int)$_POST['event_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $success = 'Evento eliminado correctamente.';
    } catch (PDOException $e) {
        $error = 'No se pudo eliminar el evento: ' . $e->getMessage();
    }
}

// Eliminar registro (ticket) de un usuario para un evento específico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $event_id = (int)$_POST['event_id'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);

        $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + 1 WHERE id = ?");
        $stmt->execute([$event_id]);

        $pdo->commit();
        $success = 'Registro del usuario eliminado del evento.';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'No se pudo eliminar el registro: ' . $e->getMessage();
    }
}

// Bloquear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_user'])) {
    $user_id = (int)$_POST['user_id'];

    if ($user_id === $admin_id) {
        $error = 'No puedes bloquear tu propia cuenta.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO blocked_users (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
            $success = 'Usuario bloqueado correctamente.';
        } catch (PDOException $e) {
            $error = 'No se pudo bloquear al usuario: ' . $e->getMessage();
        }
    }
}

// Desbloquear usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_user'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $success = 'Usuario desbloqueado correctamente.';
    } catch (PDOException $e) {
        $error = 'No se pudo desbloquear al usuario: ' . $e->getMessage();
    }
}

// Eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];

    if ($user_id === $admin_id) {
        $error = 'No puedes eliminar tu propia cuenta de administrador.';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = 'Usuario eliminado correctamente.';
        } catch (PDOException $e) {
            $error = 'No se pudo eliminar al usuario: ' . $e->getMessage();
        }
    }
}

// Ver asistentes de un evento seleccionado
$selected_event_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_attendees'])) {
    $selected_event_id = (int)$_POST['selected_event_id'];
}

// Datos para panel
$events = [];
$users = [];
$attendees = [];

try {
    $stmt = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id) AS total_registrations FROM events e ORDER BY e.event_date ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT u.*, CASE WHEN b.user_id IS NULL THEN 0 ELSE 1 END AS is_blocked
                         FROM users u
                         LEFT JOIN blocked_users b ON u.id = b.user_id
                         ORDER BY u.created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selected_event_id === null && !empty($events)) {
        $selected_event_id = (int)$events[0]['id'];
    }

    if ($selected_event_id !== null) {
        $stmt = $pdo->prepare("SELECT t.id AS ticket_id, t.purchase_date, t.status, u.id AS user_id, u.username
                               FROM tickets t
                               INNER JOIN users u ON u.id = t.user_id
                               WHERE t.event_id = ?
                               ORDER BY t.purchase_date DESC");
        $stmt->execute([$selected_event_id]);
        $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Error al cargar datos del panel: ' . $e->getMessage();
}

// Estadísticas rápidas
$totalEvents = count($events);
$totalUsers = count($users);
$totalTickets = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tickets");
    $totalTickets = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {}

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
<title>BeatPass - Panel Admin</title>
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
<span class="text-2xl font-bold gradient-text">BeatPass Admin</span>
</div>

<div class="flex items-center space-x-6">
<span class="text-purple-400 text-sm font-semibold flex items-center">
<i class="fas fa-shield-alt mr-2"></i>Administrador
</span>
<span class="text-gray-400">
<i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($admin_username); ?>
</span>
<a href="../auth.php" class="text-gray-400 hover:text-white transition text-sm">
<i class="fas fa-sign-in-alt mr-1"></i>Ir a login
</a>
<a href="../user/" class="text-gray-400 hover:text-white transition text-sm">
<i class="fas fa-home mr-1"></i>Panel usuario
</a>
<a href="../user/logout.php" class="px-4 py-2 bg-red-500/20 border border-red-500 rounded-full hover:bg-red-500/30 transition">
<i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
</a>
</div>
</div>
</nav>
</header>

<div class="container mx-auto px-6 py-8 space-y-10">
<!-- Título y estadísticas -->
<section>
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
<div>
<h1 class="text-4xl font-bold mb-2">Panel de <span class="gradient-text">Administración</span></h1>
<p class="text-gray-400 text-sm">Gestiona eventos, asistentes y usuarios de BeatPass</p>
</div>
</div>

<?php if ($error): ?>
<div class="mb-4 bg-red-500/20 border border-red-500 text-red-300 px-4 py-3 rounded-2xl">
<i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="mb-4 bg-green-500/20 border border-green-500 text-green-300 px-4 py-3 rounded-2xl">
<i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<div class="grid md:grid-cols-3 gap-6">
<div class="stat-card rounded-3xl p-6 flex items-center justify-between card-hover">
<div>
<p class="text-gray-400 text-sm">Eventos activos</p>
<p class="text-4xl font-bold gradient-text"><?php echo $totalEvents; ?></p>
</div>
<i class="fas fa-calendar-alt text-3xl text-purple-400"></i>
</div>

<div class="stat-card rounded-3xl p-6 flex items-center justify-between card-hover">
<div>
<p class="text-gray-400 text-sm">Usuarios registrados</p>
<p class="text-4xl font-bold gradient-text"><?php echo $totalUsers; ?></p>
</div>
<i class="fas fa-users text-3xl text-cyan-400"></i>
</div>

<div class="stat-card rounded-3xl p-6 flex items-center justify-between card-hover">
<div>
<p class="text-gray-400 text-sm">Tickets generados</p>
<p class="text-4xl font-bold gradient-text"><?php echo $totalTickets; ?></p>
</div>
<i class="fas fa-ticket-alt text-3xl text-pink-400"></i>
</div>
</div>
</section>

<!-- Gestión de eventos -->
<section class="space-y-6">
<div class="flex items-center justify-between">
<h2 class="text-2xl font-bold flex items-center">
<i class="fas fa-calendar-plus mr-3 text-purple-400"></i>Gestión de eventos
</h2>
</div>

<div class="grid md:grid-cols-2 gap-6">
<!-- Crear evento -->
<div class="bg-gray-900/60 rounded-3xl p-6 border border-gray-800 card-hover">
<h3 class="text-lg font-semibold mb-4 flex items-center">
<i class="fas fa-plus-circle mr-2 text-cyan-400"></i>Crear nuevo evento
</h3>

<form method="POST" class="space-y-4 text-sm">
<input type="hidden" name="create_event" value="1">

<div>
<label class="block text-gray-300 mb-1">Nombre *</label>
<input type="text" name="name" required class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" placeholder="Nombre del evento">
</div>

<div>
<label class="block text-gray-300 mb-1">Descripción</label>
<textarea name="description" rows="3" class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" placeholder="Descripción breve"></textarea>
</div>

<div>
<label class="block text-gray-300 mb-1">Lugar *</label>
<input type="text" name="location" required class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" placeholder="Ej: Estadio Nacional">
</div>

<div class="grid grid-cols-2 gap-4">
<div>
<label class="block text-gray-300 mb-1">Fecha y hora *</label>
<input type="datetime-local" name="event_date" required class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500">
</div>
<div>
<label class="block text-gray-300 mb-1">Género *</label>
<input type="text" name="genre" required class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" placeholder="Rock, Pop, etc.">
</div>
</div>

<div class="grid grid-cols-2 gap-4">
<div>
<label class="block text-gray-300 mb-1">Cupos disponibles *</label>
<input type="number" name="available_tickets" min="0" required class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" value="0">
</div>
<div>
<label class="block text-gray-300 mb-1">Precio (ARS) *</label>
<input type="number" name="price" min="0" step="0.01" required class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" value="0">
</div>
</div>

<div>
<label class="block text-gray-300 mb-1">URL de imagen (opcional)</label>
<input type="text" name="image_url" class="w-full px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500" placeholder="https://...">
</div>

<button type="submit" class="w-full py-3 mt-2 btn-gradient rounded-2xl font-semibold">
<i class="fas fa-save mr-2"></i>Crear evento
</button>
</form>
</div>

<!-- Lista de eventos -->
<div class="bg-gray-900/60 rounded-3xl p-6 border border-gray-800 card-hover">
<h3 class="text-lg font-semibold mb-4 flex items-center">
<i class="fas fa-list mr-2 text-purple-400"></i>Eventos existentes
</h3>

<?php if (!empty($events)): ?>
<div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
<?php foreach ($events as $event): ?>
<div class="bg-black/40 border border-gray-800 rounded-2xl p-4 flex flex-col gap-2">
<div class="flex items-start justify-between">
<div>
<p class="font-semibold"><?php echo htmlspecialchars($event['name']); ?></p>
<p class="text-xs text-gray-400 flex items-center">
<i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($event['location']); ?>
</p>
<p class="text-xs text-gray-400 flex items-center">
<i class="fas fa-calendar mr-1"></i><?php echo formatDate($event['event_date']); ?>
<span class="mx-1">·</span>
<i class="fas fa-clock mr-1"></i><?php echo date('H:i', strtotime($event['event_date'])); ?> HS
</p>
</div>
<form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este evento? Se eliminarán también sus tickets.');">
<input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>">
<button type="submit" name="delete_event" class="px-3 py-1 bg-red-500/20 border border-red-500 rounded-full text-xs text-red-300 hover:bg-red-500/30">
<i class="fas fa-trash-alt mr-1"></i>Eliminar
</button>
</form>
</div>

<div class="flex items-center justify-between text-xs text-gray-400">
<span><i class="fas fa-guitar mr-1"></i><?php echo htmlspecialchars($event['genre']); ?></span>
<span><i class="fas fa-users mr-1"></i><?php echo (int)$event['available_tickets']; ?> cupos libres</span>
<span><i class="fas fa-ticket-alt mr-1"></i><?php echo (int)$event['total_registrations']; ?> registrados</span>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-gray-500 text-sm">Todavía no hay eventos creados.</p>
<?php endif; ?>
</div>
</div>
</section>

<!-- Asistentes por evento -->
<section class="space-y-4">
<div class="flex items-center justify-between">
<h2 class="text-2xl font-bold flex items-center">
<i class="fas fa-users mr-3 text-cyan-400"></i>Asistentes por evento
</h2>
</div>

<?php if (!empty($events)): ?>
<form method="POST" class="mb-4 flex flex-col md:flex-row gap-3 md:items-center">
<input type="hidden" name="view_attendees" value="1">
<label class="text-sm text-gray-300">Seleccionar evento:</label>
<select name="selected_event_id" class="px-3 py-2 bg-black/50 border border-gray-700 rounded-2xl text-sm focus:outline-none focus:border-purple-500">
<?php foreach ($events as $event): ?>
<option value="<?php echo (int)$event['id']; ?>" <?php echo ($selected_event_id == $event['id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($event['name']); ?> - <?php echo formatDate($event['event_date']); ?>
</option>
<?php endforeach; ?>
</select>
<button type="submit" class="px-4 py-2 btn-gradient rounded-2xl text-sm font-semibold">
<i class="fas fa-eye mr-2"></i>Ver asistentes
</button>
</form>

<div class="bg-gray-900/60 rounded-3xl p-6 border border-gray-800">
<?php if (!empty($attendees)): ?>
<div class="overflow-x-auto text-sm">
<table class="min-w-full text-left">
<thead>
<tr class="text-gray-400 border-b border-gray-800">
<th class="py-2 pr-4">Usuario</th>
<th class="py-2 pr-4">Fecha registro</th>
<th class="py-2 pr-4">Estado</th>
<th class="py-2 pr-4 text-right">Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($attendees as $row): ?>
<tr class="border-b border-gray-800/60">
<td class="py-2 pr-4 flex items-center gap-2">
<i class="fas fa-user text-xs text-gray-500"></i>
<?php echo htmlspecialchars($row['username']); ?>
</td>
<td class="py-2 pr-4 text-gray-400">
<?php echo date('d/m/Y H:i', strtotime($row['purchase_date'])); ?>
</td>
<td class="py-2 pr-4">
<span class="px-2 py-1 rounded-full text-xs border <?php echo $row['status'] === 'used' ? 'border-green-500 text-green-300' : ($row['status'] === 'cancelled' ? 'border-red-500 text-red-300' : 'border-cyan-500 text-cyan-300'); ?>">
<?php echo htmlspecialchars($row['status']); ?>
</span>
</td>
<td class="py-2 pr-0 text-right">
<form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este registro del evento?');">
<input type="hidden" name="ticket_id" value="<?php echo (int)$row['ticket_id']; ?>">
<input type="hidden" name="event_id" value="<?php echo (int)$selected_event_id; ?>">
<button type="submit" name="remove_ticket" class="px-3 py-1 bg-red-500/20 border border-red-500 rounded-full text-xs text-red-300 hover:bg-red-500/30">
<i class="fas fa-user-minus mr-1"></i>Eliminar
</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<p class="text-gray-500 text-sm">Este evento aún no tiene usuarios registrados.</p>
<?php endif; ?>
</div>
<?php else: ?>
<p class="text-gray-500 text-sm">Crea un evento para poder ver asistentes registrados.</p>
<?php endif; ?>
</section>

<!-- Gestión de usuarios -->
<section class="space-y-4 mb-10">
<div class="flex items-center justify-between">
<h2 class="text-2xl font-bold flex items-center">
<i class="fas fa-user-cog mr-3 text-pink-400"></i>Gestión de usuarios
</h2>
</div>

<div class="bg-gray-900/60 rounded-3xl p-6 border border-gray-800">
<?php if (!empty($users)): ?>
<div class="overflow-x-auto text-sm max-h-[420px] overflow-y-auto">
<table class="min-w-full text-left">
<thead>
<tr class="text-gray-400 border-b border-gray-800">
<th class="py-2 pr-4">Usuario</th>
<th class="py-2 pr-4">Rol</th>
<th class="py-2 pr-4">Estado</th>
<th class="py-2 pr-4">Creado</th>
<th class="py-2 pr-4 text-right">Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr class="border-b border-gray-800/60">
<td class="py-2 pr-4 flex items-center gap-2">
<i class="fas fa-user text-xs text-gray-500"></i>
<?php echo htmlspecialchars($user['username']); ?>
<?php if ($user['id'] == $admin_id): ?>
<span class="ml-1 text-[10px] px-2 py-0.5 rounded-full bg-purple-500/20 border border-purple-500 text-purple-300">Tú</span>
<?php endif; ?>
</td>
<td class="py-2 pr-4">
<span class="px-2 py-1 rounded-full text-xs border <?php echo $user['role'] === 'admin' ? 'border-purple-500 text-purple-300' : 'border-gray-500 text-gray-300'; ?>">
<?php echo htmlspecialchars($user['role']); ?>
</span>
</td>
<td class="py-2 pr-4">
<?php if ($user['is_blocked']): ?>
<span class="px-2 py-1 rounded-full text-xs border border-red-500 text-red-300 flex items-center gap-1">
<i class="fas fa-ban"></i> Bloqueado
</span>
<?php else: ?>
<span class="px-2 py-1 rounded-full text-xs border border-green-500 text-green-300 flex items-center gap-1">
<i class="fas fa-check"></i> Activo
</span>
<?php endif; ?>
</td>
<td class="py-2 pr-4 text-gray-400">
<?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
</td>
<td class="py-2 pr-0 text-right">
<div class="flex justify-end gap-2">
<?php if ($user['id'] != $admin_id): ?>
<form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario? Se eliminarán también sus tickets y eventos asociados.');">
<input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
<button type="submit" name="delete_user" class="px-3 py-1 bg-red-500/20 border border-red-500 rounded-full text-xs text-red-300 hover:bg-red-500/30">
<i class="fas fa-user-slash mr-1"></i>Eliminar
</button>
</form>

<?php if (!$user['is_blocked']): ?>
<form method="POST" onsubmit="return confirm('¿Seguro que deseas bloquear el acceso de este usuario?');">
<input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
<button type="submit" name="block_user" class="px-3 py-1 bg-yellow-500/20 border border-yellow-500 rounded-full text-xs text-yellow-300 hover:bg-yellow-500/30">
<i class="fas fa-ban mr-1"></i>Bloquear
</button>
</form>
<?php else: ?>
<form method="POST" onsubmit="return confirm('¿Seguro que deseas desbloquear este usuario?');">
<input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
<button type="submit" name="unblock_user" class="px-3 py-1 bg-green-500/20 border border-green-500 rounded-full text-xs text-green-300 hover:bg-green-500/30">
<i class="fas fa-unlock mr-1"></i>Desbloquear
</button>
</form>
<?php endif; ?>
<?php else: ?>
<span class="text-xs text-gray-500 italic">No puedes modificar tu propia cuenta desde aquí.</span>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<p class="text-gray-500 text-sm">Todavía no hay usuarios registrados.</p>
<?php endif; ?>
</div>
</section>
</div>

</body>
</html>

