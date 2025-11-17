<?php
session_start();

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'beatpass');
define('DB_USER', 'beatpass');
define('DB_PASS', 'beatpass');

// Conexión a la base de datos
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
        CONSTRAINT fk_blocked_user_auth FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (PDOException $e) {
    // En caso de error, simplemente no se habilita el bloqueo desde esta instancia
}

// Variables
$view = isset($_GET['view']) ? $_GET['view'] : 'login';
$error = '';
$success = '';

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } else {
        // Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $error = 'El nombre de usuario ya está en uso';
        } else {
            // Cifrar password y crear usuario
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");

            if ($stmt->execute([$username, $hashedPassword])) {
                $success = 'Registro exitoso. Redirigiendo...';
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                header("refresh:2;url=./user/");
            } else {
                $error = 'Error al registrar el usuario';
            }
        }
    }
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Verificar si el usuario está bloqueado
            try {
                $stmtBlocked = $pdo->prepare("SELECT 1 FROM blocked_users WHERE user_id = ? LIMIT 1");
                $stmtBlocked->execute([$user['id']]);
                $isBlocked = $stmtBlocked->fetchColumn();
            } catch (PDOException $e) {
                $isBlocked = false;
            }

            if ($isBlocked) {
                $error = 'Tu cuenta ha sido bloqueada por un administrador.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirigir según el rol
                if ($user['role'] === 'admin') {
                    header("Location: ./admin/");
                } else {
                    header("Location: ./user/");
                }
                exit();
            }
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BeatPass - <?php echo $view === 'register' ? 'Registro' : 'Iniciar Sesión'; ?></title>
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

.form-transition {
    transition: all 0.5s ease;
}

.login-form {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    border: 2px solid rgba(168, 85, 247, 0.3);
}

.register-form {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
    border: 2px solid rgba(6, 182, 212, 0.3);
}

.tab-active {
    background: linear-gradient(135deg, #a855f7 0%, #06b6d4 100%);
    color: white;
}

.tab-inactive {
    background: rgba(255, 255, 255, 0.05);
    color: #9ca3af;
}

.tab-inactive:hover {
    background: rgba(255, 255, 255, 0.1);
}
</style>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center px-6">
<div class="w-full max-w-md">
<!-- Logo -->
<div class="text-center mb-8">
<div class="flex items-center justify-center space-x-2 mb-4">
<i class="fas fa-music text-4xl gradient-text"></i>
<span class="text-3xl font-bold gradient-text">BeatPass</span>
</div>
<p class="text-gray-400">La música nos une, la experiencia nos conecta</p>
</div>

<!-- Tabs -->
<div class="flex rounded-t-3xl overflow-hidden mb-0">
<a href="?view=login"
class="flex-1 py-4 text-center font-semibold transition <?php echo $view === 'login' ? 'tab-active' : 'tab-inactive'; ?>">
Iniciar Sesión
</a>
<a href="?view=register"
class="flex-1 py-4 text-center font-semibold transition <?php echo $view === 'register' ? 'tab-active' : 'tab-inactive'; ?>">
Registrarse
</a>
</div>

<!-- Formulario -->
<div class="form-transition rounded-b-3xl rounded-t-none p-8 <?php echo $view === 'register' ? 'register-form' : 'login-form'; ?>">
<?php if ($error): ?>
<div class="bg-red-500/20 border border-red-500 text-red-300 px-4 py-3 rounded-2xl mb-6">
<i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="bg-green-500/20 border border-green-500 text-green-300 px-4 py-3 rounded-2xl mb-6">
<i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
</div>
<?php endif; ?>

<?php if ($view === 'login'): ?>
<!-- Formulario de Login -->
<form method="POST" action="?view=login" class="space-y-6">
<div>
<label class="block text-sm font-semibold mb-2 text-gray-300">
<i class="fas fa-user mr-2"></i>Nombre de Usuario
</label>
<input type="text"
name="username"
required
class="w-full px-4 py-3 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500 transition text-white"
placeholder="Ingresa tu usuario">
</div>

<div>
<label class="block text-sm font-semibold mb-2 text-gray-300">
<i class="fas fa-lock mr-2"></i>Contraseña
</label>
<input type="password"
name="password"
required
class="w-full px-4 py-3 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-purple-500 transition text-white"
placeholder="Ingresa tu contraseña">
</div>

<button type="submit"
name="login"
class="w-full py-4 btn-gradient rounded-2xl text-white font-semibold text-lg">
Iniciar Sesión
</button>
</form>
<?php else: ?>
<!-- Formulario de Registro -->
<form method="POST" action="?view=register" class="space-y-6">
<div>
<label class="block text-sm font-semibold mb-2 text-gray-300">
<i class="fas fa-user mr-2"></i>Nombre de Usuario
</label>
<input type="text"
name="username"
required
class="w-full px-4 py-3 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-cyan-500 transition text-white"
placeholder="Elige un nombre de usuario">
</div>

<div>
<label class="block text-sm font-semibold mb-2 text-gray-300">
<i class="fas fa-lock mr-2"></i>Contraseña
</label>
<input type="password"
name="password"
required
class="w-full px-4 py-3 bg-black/50 border border-gray-700 rounded-2xl focus:outline-none focus:border-cyan-500 transition text-white"
placeholder="Crea una contraseña segura">
</div>

<button type="submit"
name="register"
class="w-full py-4 btn-gradient rounded-2xl text-white font-semibold text-lg">
Crear Cuenta
</button>
</form>
<?php endif; ?>

<!-- Link alternativo -->
<div class="mt-6 text-center text-gray-400">
<?php if ($view === 'login'): ?>
¿No tienes cuenta?
<a href="?view=register" class="text-purple-400 hover:text-purple-300 font-semibold">
Regístrate aquí
</a>
<?php else: ?>
¿Ya tienes cuenta?
<a href="?view=login" class="text-cyan-400 hover:text-cyan-300 font-semibold">
Inicia sesión
</a>
<?php endif; ?>
</div>
</div>

<!-- Volver a inicio -->
<div class="text-center mt-8">
<a href="../index.html" class="text-gray-400 hover:text-purple-400 transition">
<i class="fas fa-arrow-left mr-2"></i>Volver al inicio
</a>
</div>
</div>
</body>
</html>
