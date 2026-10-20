<?php
session_start();

// Si ya inició sesión, redirigir directo al sistema
if (isset($_SESSION['usuario_id'])) {
   header('Location: dashboard.php');
    exit;
}

require_once 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (!empty($usuario) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND estado = 'activo' LIMIT 1");
        $stmt->execute(array($usuario));
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Guardar datos de sesión
            $_SESSION['usuario_id']     = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre_completo'];
            $_SESSION['usuario_login']  = $user['usuario'];
            $_SESSION['usuario_rol']    = $user['rol'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas o usuario inactivo.';
        }
    } else {
        $error = 'Por favor complete todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso al Sistema CAS - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
</head>
<body class="bg-[#0c1526] min-h-screen flex items-center justify-center p-4 antialiased selection:bg-[#008f5d] selection:text-white relative overflow-hidden">

  <!-- Elementos decorativos de fondo -->
  <div class="absolute -top-40 -left-40 w-96 h-96 bg-[#008f5d]/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-[#00a86b]/10 rounded-full blur-3xl pointer-events-none"></div>

  <div class="w-full max-w-md bg-white rounded-3xl p-8 sm:p-10 shadow-[0_20px_50px_rgba(0,0,0,0.35)] border border-slate-100 relative z-10">
    
    <!-- Encabezado Institucional -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-[#0c1526] to-[#1c2c49] border-2 border-[#00a86b]/40 shadow-lg p-3.5 mb-4 group">
        <svg class="w-full h-full text-[#00c982]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
        </svg>
      </div>
      
      <span class="inline-block px-3 py-1 bg-[#e6f7ef] text-[#008f5d] text-[10px] font-extrabold uppercase tracking-widest rounded-full mb-1">
        UGEL Ventanilla
      </span>
      <h2 class="text-xl font-extrabold text-[#111827] tracking-tight">Sistema Integrado CAS</h2>
      <p class="text-xs text-slate-400 mt-1">Gestión y Planillas - D.L. 1057</p>
    </div>

    <!-- Mensaje de Error -->
    <?php if (!empty($error)): ?>
      <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 text-rose-600 rounded-xl text-xs font-semibold flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <!-- Formulario de Acceso -->
    <form action="login.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Usuario</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </span>
          <input type="text" name="usuario" required autocomplete="username" placeholder="Ingresa tu usuario institucional" 
                 class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#008f5d]/30 focus:border-[#008f5d] transition-all">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Contraseña</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          </span>
          <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••" 
                 class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#008f5d]/30 focus:border-[#008f5d] transition-all">
        </div>
      </div>

      <div class="pt-2">
        <button type="submit" class="w-full py-3 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-[0_4px_14px_rgba(0,143,93,0.35)] transition-all active:scale-95 flex items-center justify-center gap-2">
          <span>Ingresar al Sistema</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
      </div>
    </form>

    <!-- Pie institucional -->
    <div class="mt-8 pt-4 border-t border-slate-100 text-center text-[11px] text-slate-400 font-medium">
      Unidad de Gestión Educativa Local de Ventanilla<br>Área de Recursos Humanos - Planillas
    </div>
  </div>

</body>
</html>