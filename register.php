<!-- <?php
include 'config.php';

if (isset($_POST['register'])) {
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $password = $_POST['password'];
    $role = 'siswa'; 

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Username sudah terdaftar!";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, password, role) VALUES ('$username', '$password_hash', '$role')";
        
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Registrasi Berhasil! Silakan login.'); window.location='login.php';</script>";
        } else {
            $error = "Terjadi kesalahan: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center p-6">
    <div class="bg-white/95 backdrop-blur-sm p-8 rounded-2xl shadow-2xl w-full max-w-md border border-white/20">
        <div class="text-center mb-8">
            <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-plus text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-800">Daftar Akun Baru</h2>
            <p class="text-gray-500">Isi data diri untuk bergabung</p>
        </div>
        
        <?php if (isset($error)) echo "<div class='bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-center text-sm'>$error</div>"; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="username" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Buat username" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <input type="password" name="password" class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="Buat password" required>
            </div>
            <button type="submit" name="register" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transform transition active:scale-95">Daftar Sekarang</button>
        </form>
        <p class="text-center mt-6 text-gray-600 text-sm">Sudah punya akun? <a href="login.php" class="text-blue-600 font-bold hover:underline">Login disini</a></p>
    </div>
</body>
</html> -->