<?php 
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'];
$result = mysqli_query($conn, "SELECT * FROM books WHERE id=$id");
$book = mysqli_fetch_assoc($result);

if (!$book) {
    header("Location: admin.php");
    exit();
}

if (isset($_POST['update'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $stock = (int)$_POST['stock'];

    $query = "UPDATE books SET title='$title', author='$author', stock=$stock WHERE id=$id";
    
    if (mysqli_query($conn, $query)) {
        header("Location: admin.php?msg=Buku berhasil diperbarui!");
        exit();
    } else {
        $error = "Gagal memperbarui data.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Edit Buku - PerpusSmecha</title>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen p-6">
    <div class="bg-white p-8 rounded-3xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="flex items-center gap-3 mb-8">
            <div class="bg-blue-600 p-2 rounded-lg text-white">
                <i class="fas fa-edit"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800">Edit Data Buku</h2>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Judul Buku</label>
                <input type="text" name="title" value="<?= $book['title'] ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Penulis</label>
                <input type="text" name="author" value="<?= $book['author'] ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Jumlah Stok</label>
                <input type="number" name="stock" value="<?= $book['stock'] ?>" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
            </div>
            
            <div class="flex gap-3 pt-4">
                <a href="admin.php" class="w-1/2 text-center bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition">Batal</a>
                <button type="submit" name="update" class="w-1/2 bg-blue-600 text-white font-bold py-3 rounded-xl shadow-lg hover:bg-blue-700 transition active:scale-95">Simpan</button>
            </div>
        </form>
    </div>
</body>
</html>