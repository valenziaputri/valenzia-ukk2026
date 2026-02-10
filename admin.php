<?php 
include 'config.php';
session_start();

// Cek Login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Cek & Tambah Kolom Created At jika belum ada
 $check_col = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'created_at'");
if(mysqli_num_rows($check_col) == 0){
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// --- PROSES FORM (LOGIKA BACKEND) ---

// LOGIKA UPDATE BUKU
if (isset($_POST['update_book'])) {
    $id = (int)$_POST['book_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    mysqli_query($conn, "UPDATE books SET title='$title', author='$author' WHERE id=$id");
    header("Location: admin.php?section=books_list&msg=Data Buku Berhasil Diupdate");
    exit();
}

// LOGIKA UPDATE KONDISI (Dibiarkan backendnya berjalan jika akses langsung, tapi tidak ada menu UI)
if (isset($_POST['update_condition'])) {
    $id = (int)$_POST['book_id'];
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $persentase = (int)$_POST['persentase'];
    mysqli_query($conn, "UPDATE books SET kondisi='$kondisi', persentase_kondisi='$persentase' WHERE id=$id");
    header("Location: admin.php?section=books_list&msg=Kondisi Buku Diperbarui"); // Redirect ke list
    exit();
}

if (isset($_POST['add_book'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $kondisi = "Baik";
    $persentase = 100;
    $query = "INSERT INTO books (title, author, kondisi, persentase_kondisi) VALUES ('$title', '$author', '$kondisi', '$persentase')";
    mysqli_query($conn, $query);
    header("Location: admin.php?section=books_list&msg=Buku Berhasil Ditambahkan");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM loans WHERE book_id = $id");
    mysqli_query($conn, "DELETE FROM books WHERE id = $id");
    header("Location: admin.php?section=books_list&msg=Buku Berhasil Dihapus");
    exit();
}

if (isset($_POST['add_member'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$username', '$hashed_password', 'siswa')");
    header("Location: admin.php?section=members_list&msg=Anggota Berhasil Ditambahkan");
    exit();
}

if (isset($_POST['update_member'])) {
    $id = (int)$_POST['member_id'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    if (!empty($_POST['password'])) {
        $new_pass = mysqli_real_escape_string($conn, $_POST['password']);
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET username='$username', password='$hashed_password', role='$role' WHERE id=$id");
    } else {
        mysqli_query($conn, "UPDATE users SET username='$username', role='$role' WHERE id=$id");
    }
    header("Location: admin.php?section=members_list&msg=Data Anggota Berhasil Diperbarui");
    exit();
}

if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    mysqli_query($conn, "DELETE FROM loans WHERE user_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
    header("Location: admin.php?section=members_list&msg=Anggota Berhasil Dihapus");
    exit();
}

if (isset($_POST['add_loan'])) {
    $user_id = (int)$_POST['user_id'];
    $book_id = (int)$_POST['book_id'];
    $loan_date = date('Y-m-d');
    $return_date = date('Y-m-d', strtotime($loan_date . ' + 7 days'));
    $check_loan = mysqli_query($conn, "SELECT id FROM loans WHERE book_id = $book_id AND status = 'dipinjam' LIMIT 1");
    
    if (mysqli_num_rows($check_loan) == 0) {
        mysqli_query($conn, "INSERT INTO loans (user_id, book_id, loan_date, return_date, status) VALUES ('$user_id', '$book_id', '$loan_date', '$return_date', 'dipinjam')");
        header("Location: admin.php?section=loans_active&msg=Peminjaman Berhasil Ditambahkan");
    } else {
        header("Location: admin.php?section=add_loan&msg=Gagal: Buku sedang dipinjam orang lain!");
    }
    exit();
}

if (isset($_GET['delete_loan'])) {
    $loan_id = (int)$_GET['delete_loan'];
    $data = mysqli_query($conn, "SELECT book_id FROM loans WHERE id=$loan_id");
    if(mysqli_num_rows($data) > 0){
        mysqli_query($conn, "DELETE FROM loans WHERE id=$loan_id");
    }
    header("Location: admin.php?section=loans_active&msg=Peminjaman Berhasil Dihapus");
    exit();
}

if (isset($_GET['kembalikan'])) {
    $loan_id = (int)$_GET['kembalikan'];
    $loan_data = mysqli_query($conn, "SELECT book_id FROM loans WHERE id=$loan_id");
    if(mysqli_num_rows($loan_data) > 0){
        mysqli_query($conn, "UPDATE loans SET status='kembali' WHERE id=$loan_id");
    }
    header("Location: admin.php?section=loans_active&msg=Buku Telah Dikembalikan");
    exit();
}

// --- AMBIL DATA STATISTIK ---
 $count_books = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM books"));
 $count_members = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role='siswa'"));
 $count_loans_active = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM loans WHERE status='dipinjam'"));

// --- PENGATURAN TAB / SECTION ---
 $section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// --- LOGIKA FILTER & QUERY ---

// 1. Data Buku
 $search_books = isset($_GET['search_books']) ? mysqli_real_escape_string($conn, $_GET['search_books']) : '';
 $date_books = isset($_GET['date_books']) ? $_GET['date_books'] : '';

 $query_books = "SELECT * FROM books WHERE 1=1";
if ($search_books) $query_books .= " AND (title LIKE '%$search_books%' OR author LIKE '%$search_books%')";
if ($date_books) $query_books .= " AND DATE(created_at) = '$date_books'";
 $query_books .= " ORDER BY id DESC";
 $books = mysqli_query($conn, $query_books);

// 2. Data Kondisi (Query disimpan jika dibutuhkan backend, tapi UI dihapus)
 $filter_kondisi = isset($_GET['filter_kondisi']) ? $_GET['filter_kondisi'] : '';
 $query_condition = "SELECT * FROM books WHERE 1=1";
if ($filter_kondisi) {
    $safe_filter = mysqli_real_escape_string($conn, $filter_kondisi);
    $query_condition .= " AND kondisi = '$safe_filter'";
}
 $query_condition .= " ORDER BY id DESC";
 $condition_books = mysqli_query($conn, $query_condition);

// 3. Data Anggota
 $search_members = isset($_GET['search_members']) ? mysqli_real_escape_string($conn, $_GET['search_members']) : '';
 $query_members = "SELECT * FROM users WHERE role='siswa'";
if ($search_members) $query_members .= " AND username LIKE '%$search_members%'";
 $query_members .= " ORDER BY id DESC";
 $members = mysqli_query($conn, $query_members);

// 4. Data Peminjaman Aktif
 $search_loans = isset($_GET['search_loans']) ? mysqli_real_escape_string($conn, $_GET['search_loans']) : '';
 $date_loans = isset($_GET['date_loans']) ? $_GET['date_loans'] : '';

 $query_loans = "SELECT loans.*, users.username, books.title
          FROM loans JOIN users ON loans.user_id = users.id
          JOIN books ON loans.book_id = books.id
          WHERE status = 'dipinjam'";
if ($search_loans) $query_loans .= " AND (users.username LIKE '%$search_loans%' OR books.title LIKE '%$search_loans%')";
if ($date_loans) $query_loans .= " AND DATE(loan_date) = '$date_loans'";
 $query_loans .= " ORDER BY loan_date ASC";
 $loans = mysqli_query($conn, $query_loans);

// 5. Data Riwayat
 $search_history = isset($_GET['search_history']) ? mysqli_real_escape_string($conn, $_GET['search_history']) : '';
 $query_history = "SELECT loans.*, users.username, books.title
         FROM loans JOIN users ON loans.user_id = users.id
         JOIN books ON loans.book_id = books.id
         WHERE status != 'dipinjam'";
if ($search_history) $query_history .= " AND (users.username LIKE '%$search_history%' OR books.title LIKE '%$search_history%')";
 $query_history .= " ORDER BY loan_date DESC";
 $history = mysqli_query($conn, $query_history);

// Data Dropdowns 
 $all_members = mysqli_query($conn, "SELECT * FROM users WHERE role='siswa' ORDER BY username ASC");
 $all_books_drop = mysqli_query($conn, "SELECT * FROM books ORDER BY title ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Dashboard Admin - PerpusSmecha</title>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* --- STYLE KHUSUS PRINT --- */
        @media print {
            .no-print, nav, #sidebar, button, input[type="date"], input[type="text"], form, .print-controls { display: none !important; }
            body, .flex, .lg\:ml-72, main, .max-w-7xl { width: 100% !important; margin: 0 !important; padding: 0 !important; position: static !important; overflow: visible !important; height: auto !important; }
            table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 20px !important; font-size: 12px !important; }
            th, td { border: 1px solid #000 !important; padding: 8px !important; text-align: left !important; color: #000 !important; }
            th { background-color: #f3f4f6 !important; font-weight: bold !important; -webkit-print-color-adjust: exact; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px !important; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .no-print-col { display: none !important; }
        }
        .print-header { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 h-screen flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-72 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col h-full fixed z-40 transition-transform duration-300 transform -translate-x-full lg:translate-x-0">
        <div class="h-16 flex items-center justify-center border-b border-slate-100 bg-gradient-to-r from-blue-600 to-indigo-600 flex-shrink-0">
            <h1 class="text-xl font-bold text-white tracking-wide">PERPUS SMECHA</h1>
        </div>

        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-2 no-scrollbar">
            <div class="text-xs font-bold text-slate-400 uppercase px-3 mb-1">Menu Utama</div>
            <a href="?section=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= $section == 'dashboard' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                <i class="fas fa-home w-5 text-center"></i> Dashboard
            </a>

            <div class="text-xs font-bold text-slate-400 uppercase px-3 mt-6 mb-1">Master Buku</div>
            <div class="group">
                <button onclick="toggleSubmenu('menuBuku')" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-600 rounded-xl hover:bg-slate-50 transition">
                    <div class="flex items-center gap-3"><i class="fas fa-book w-5 text-center"></i> Buku</div>
                    <i class="fas fa-chevron-down text-xs transition-transform <?= in_array($section, ['add_book', 'books_list']) ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="menuBuku" class="pl-10 pr-2 mt-1 space-y-1 <?= in_array($section, ['add_book', 'books_list']) ? '' : 'hidden' ?>">
                    <a href="?section=add_book" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'add_book' ? 'bg-blue-50 text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">+ Tambah Buku</a>
                    <a href="?section=books_list" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'books_list' ? 'bg-blue-50 text-blue-600 font-bold' : 'text-slate-500 hover:text-blue-600' ?>">Katalog Buku</a>
                    <!-- MENU KONDISI BUKU DIHAPUS -->
                </div>
            </div>

            <div class="text-xs font-bold text-slate-400 uppercase px-3 mt-6 mb-1">Master Anggota</div>
            <div class="group">
                <button onclick="toggleSubmenu('menuAnggota')" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-600 rounded-xl hover:bg-slate-50 transition">
                    <div class="flex items-center gap-3"><i class="fas fa-users w-5 text-center"></i> Anggota</div>
                    <i class="fas fa-chevron-down text-xs transition-transform <?= in_array($section, ['add_member', 'members_list']) ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="menuAnggota" class="pl-10 pr-2 mt-1 space-y-1 <?= in_array($section, ['add_member', 'members_list']) ? '' : 'hidden' ?>">
                    <a href="?section=add_member" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'add_member' ? 'bg-purple-50 text-purple-600 font-bold' : 'text-slate-500 hover:text-purple-600' ?>">Tambah Anggota</a>
                    <a href="?section=members_list" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'members_list' ? 'bg-purple-50 text-purple-600 font-bold' : 'text-slate-500 hover:text-purple-600' ?>">Kelola Anggota</a>
                </div>
            </div>

            <div class="text-xs font-bold text-slate-400 uppercase px-3 mt-6 mb-1">Transaksi</div>
            <div class="group">
                <button onclick="toggleSubmenu('menuPinjam')" class="w-full flex items-center justify-between px-3 py-2.5 text-slate-600 rounded-xl hover:bg-slate-50 transition">
                    <div class="flex items-center gap-3"><i class="fas fa-hand-holding-heart w-5 text-center"></i> Peminjaman</div>
                    <i class="fas fa-chevron-down text-xs transition-transform <?= in_array($section, ['add_loan', 'loans_active', 'loans_history']) ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="menuPinjam" class="pl-10 pr-2 mt-1 space-y-1 <?= in_array($section, ['add_loan', 'loans_active', 'loans_history']) ? '' : 'hidden' ?>">
                    <a href="?section=add_loan" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'add_loan' ? 'bg-orange-50 text-orange-600 font-bold' : 'text-slate-500 hover:text-orange-600' ?>">+ Input Peminjaman</a>
                    <a href="?section=loans_active" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'loans_active' ? 'bg-orange-50 text-orange-600 font-bold' : 'text-slate-500 hover:text-orange-600' ?>">Sedang Dipinjam</a>
                    <a href="?section=loans_history" class="block px-3 py-2 text-xs rounded-lg transition <?= $section == 'loans_history' ? 'bg-orange-50 text-orange-600 font-bold' : 'text-slate-500 hover:text-orange-600' ?>">Riwayat Pinjam</a>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-slate-100 flex-shrink-0">
            <a href="logout.php" onclick="return confirm('Logout?')" class="flex items-center justify-center gap-2 w-full bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-full lg:ml-72 transition-all duration-300">
        <nav class="h-16 bg-white border-b border-slate-200 flex justify-between items-center px-6 lg:px-8 flex-shrink-0 z-30">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-blue-600"><i class="fas fa-bars text-xl"></i></button>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 leading-none">Dashboard Admin</h2>
                    <p class="text-xs text-slate-500 mt-1">Selamat datang, <?= $_SESSION['username'] ?></p>
                </div>
            </div>
            <a href="admin.php" class="text-slate-400 hover:text-blue-600 transition"><i class="fas fa-sync-alt"></i></a>
        </nav>

        <main class="flex-1 overflow-y-auto p-4 lg:p-8 relative">
            <?php if(isset($_GET['msg'])): ?>
                <div class="max-w-7xl mx-auto mb-6">
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm flex items-center justify-between">
                        <span><i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['msg']) ?></span>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>?section=<?= $section ?>" class="text-green-800 hover:underline font-bold text-xs uppercase ml-4">Tutup</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="max-w-7xl mx-auto fade-in">
                
                <!-- DASHBOARD -->
                <?php if($section == 'dashboard'): ?>
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">Selamat Datang!</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-orange-400 to-orange-500 rounded-3xl p-6 text-white shadow-lg shadow-orange-200 transform hover:scale-105 transition">
                            <div class="flex justify-between items-start">
                                <div><p class="text-orange-100 text-sm font-medium uppercase tracking-wider">Sedang Dipinjam</p><h3 class="text-4xl font-bold mt-2"><?= $count_loans_active ?></h3></div>
                                <div class="bg-white/20 p-3 rounded-2xl backdrop-blur-sm"><i class="fas fa-book-open text-2xl"></i></div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-3xl p-6 text-white shadow-lg shadow-blue-200 transform hover:scale-105 transition">
                            <div class="flex justify-between items-start">
                                <div><p class="text-blue-100 text-sm font-medium uppercase tracking-wider">Katalog Buku</p><h3 class="text-4xl font-bold mt-2"><?= $count_books ?></h3></div>
                                <div class="bg-white/20 p-3 rounded-2xl backdrop-blur-sm"><i class="fas fa-layer-group text-2xl"></i></div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-3xl p-6 text-white shadow-lg shadow-purple-200 transform hover:scale-105 transition">
                            <div class="flex justify-between items-start">
                                <div><p class="text-purple-100 text-sm font-medium uppercase tracking-wider">Total Anggota</p><h3 class="text-4xl font-bold mt-2"><?= $count_members ?></h3></div>
                                <div class="bg-white/20 p-3 rounded-2xl backdrop-blur-sm"><i class="fas fa-users text-2xl"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                        <h3 class="font-bold text-slate-700 mb-4">Aksi Cepat</h3>
                        <div class="flex gap-4 flex-wrap">
                            <a href="?section=add_loan" class="flex-1 min-w-[150px] bg-orange-50 border border-orange-100 p-4 rounded-2xl flex flex-col items-center justify-center hover:bg-orange-100 transition"><i class="fas fa-plus-circle text-orange-500 text-2xl mb-2"></i><span class="text-sm font-bold text-orange-700">Pinjam Buku</span></a>
                            <a href="?section=add_book" class="flex-1 min-w-[150px] bg-blue-50 border border-blue-100 p-4 rounded-2xl flex flex-col items-center justify-center hover:bg-blue-100 transition"><i class="fas fa-book-medical text-blue-500 text-2xl mb-2"></i><span class="text-sm font-bold text-blue-700">Tambah Buku</span></a>
                            <a href="?section=add_member" class="flex-1 min-w-[150px] bg-purple-50 border border-purple-100 p-4 rounded-2xl flex flex-col items-center justify-center hover:bg-purple-100 transition"><i class="fas fa-user-plus text-purple-500 text-2xl mb-2"></i><span class="text-sm font-bold text-purple-700">Tambah Siswa</span></a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECTION BUKU: KATALOG (KOLOM KONDISI DIHAPUS) -->
                <?php if($section == 'books_list'): ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden print-content">
                        <div class="p-5 border-b border-slate-50 bg-blue-50/50 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 no-print">
                            <h2 class="font-bold text-blue-700 uppercase text-xs tracking-wider"><i class="fas fa-list mr-2"></i> Katalog Data Buku</h2>
                            <div class="flex gap-2 w-full lg:w-auto items-center">
                                <form method="GET" class="relative w-full lg:w-48">
                                    <input type="hidden" name="section" value="books_list">
                                    <input type="date" name="date_books" value="<?= $date_books ?>" onchange="this.form.submit()" class="w-full px-2 py-1.5 text-xs border border-blue-200 rounded-lg focus:ring-1 focus:ring-blue-300 outline-none">
                                </form>
                                <form method="GET" class="relative w-full lg:w-48">
                                    <input type="hidden" name="section" value="books_list">
                                    <input type="hidden" name="date_books" value="<?= $date_books ?>">
                                    <input type="text" name="search_books" value="<?= htmlspecialchars($search_books) ?>" placeholder="Cari buku..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-blue-200 rounded-lg focus:ring-1 focus:ring-blue-300 outline-none">
                                    <i class="fas fa-search absolute left-2.5 top-2 text-blue-300 text-[10px]"></i>
                                </form>
                                <button onclick="window.print()" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-black transition"><i class="fas fa-print"></i> PDF</button>
                            </div>
                        </div>

                        <div class="print-header">
                            <h1 class="text-xl font-bold uppercase mb-1">Laporan Katalog Buku</h1>
                            <p class="text-sm">Dicetak: <?= date('d-m-Y') ?></p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                    <tr>
                                        <th class="p-4">Judul</th>
                                        <th class="p-4">Penulis</th>
                                        <th class="p-4 text-center">Status Ketersediaan</th>
                                        <!-- KOLOM KONDISI DIHAPUS -->
                                        <th class="p-4 no-print-col">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php while($row = mysqli_fetch_assoc($books)): 
                                        $cek_status = mysqli_query($conn, "SELECT id FROM loans WHERE book_id = " . $row['id'] . " AND status='dipinjam' LIMIT 1");
                                        $is_borrowed = (mysqli_num_rows($cek_status) > 0);
                                    ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-bold text-slate-800"><?= $row['title'] ?></td>
                                        <td class="p-4 text-slate-600"><?= $row['author'] ?></td>
                                        <td class="p-4 text-center">
                                            <?php if($is_borrowed): ?>
                                                <span class="bg-red-100 text-red-700 border border-red-200 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide">
                                                    <i class="fas fa-hand-holding mr-1"></i> Terpinjam
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-green-100 text-green-700 border border-green-200 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide">
                                                    <i class="fas fa-check-circle mr-1"></i> Ready
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-center no-print-col">
                                            <button onclick="openEditBookModal(<?= $row['id'] ?>, '<?= addslashes($row['title']) ?>', '<?= addslashes($row['author']) ?>')" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white w-8 h-8 rounded-xl flex items-center justify-center transition"><i class="fas fa-edit text-xs"></i></button>
                                            <a href="?delete=<?= $row['id'] ?>&section=books_list" onclick="return confirm('Hapus buku ini?')" class="text-red-400 hover:text-red-600 p-2 rounded-lg hover:bg-red-50 transition"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECTION BUKU: TAMBAH -->
                <?php if($section == 'add_book'): ?>
                    <div class="max-w-lg mx-auto bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
                        <div class="text-center mb-6"><div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600 text-2xl"><i class="fas fa-book"></i></div><h2 class="text-xl font-bold text-slate-800">Tambah Buku Baru</h2></div>
                        <form method="POST" action="" class="space-y-4">
                            <div><label class="text-xs font-bold text-slate-500 uppercase">Judul Buku</label><input type="text" name="title" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-300 outline-none" required></div>
                            <div><label class="text-xs font-bold text-slate-500 uppercase">Penulis</label><input type="text" name="author" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-300 outline-none" required></div>
                            <button type="submit" name="add_book" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition mt-4">Simpan Buku</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- SECTION KONDISI BUKU DIHAPUS (HTML) -->

                <!-- SECTION ANGGOTA -->
                <?php if($section == 'add_member'): ?>
                    <div class="max-w-lg mx-auto bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
                        <div class="text-center mb-6"><div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-purple-600 text-2xl"><i class="fas fa-user-plus"></i></div><h2 class="text-xl font-bold text-slate-800">Tambah Anggota Siswa</h2></div>
                        <form method="POST" action="" class="space-y-4">
                            <div><label class="text-xs font-bold text-slate-500 uppercase">Username</label><input type="text" name="username" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-purple-300 outline-none" required></div>
                            <div><label class="text-xs font-bold text-slate-500 uppercase">Password</label><input type="password" name="password" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-purple-300 outline-none" required></div>
                            <button type="submit" name="add_member" class="w-full bg-purple-600 text-white font-bold py-3 rounded-xl hover:bg-purple-700 transition mt-4">Simpan Anggota</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if($section == 'members_list'): ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-5 border-b border-slate-50 bg-purple-50/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 no-print">
                            <h2 class="font-bold text-purple-700 uppercase text-xs tracking-wider"><i class="fas fa-users mr-2"></i> Data Anggota</h2>
                            <form method="GET" class="relative w-full sm:w-64">
                                <input type="hidden" name="section" value="members_list">
                                <input type="text" name="search_members" value="<?= htmlspecialchars($search_members) ?>" placeholder="Cari username..." class="w-full pl-10 pr-4 py-2 text-xs border border-purple-200 rounded-xl focus:ring-2 focus:ring-purple-300 outline-none bg-white">
                                <i class="fas fa-search absolute left-4 top-2.5 text-purple-300 text-xs"></i>
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                    <tr><th class="p-4">Username</th><th class="p-4">Role</th><th class="p-4 text-center">Aksi</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php while($m = mysqli_fetch_assoc($members)): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-bold text-slate-700 flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xs"><i class="fas fa-user"></i></div><?= $m['username'] ?></td>
                                        <td class="p-4"><span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] uppercase"><?= $m['role'] ?></span></td>
                                        <td class="p-4 text-center flex justify-center gap-2">
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($m)) ?>)" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white w-8 h-8 rounded-xl flex items-center justify-center transition"><i class="fas fa-edit text-xs"></i></button>
                                            <a href="?delete_user=<?= $m['id'] ?>&section=members_list" onclick="return confirm('Hapus?')" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white w-8 h-8 rounded-xl flex items-center justify-center transition"><i class="fas fa-trash text-xs"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECTION PEMINJAMAN -->
                <?php if($section == 'add_loan'): ?>
                    <div class="max-w-lg mx-auto bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
                        <div class="text-center mb-6"><div class="bg-orange-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-orange-600 text-2xl"><i class="fas fa-hand-holding-heart"></i></div><h2 class="text-xl font-bold text-slate-800">Input Peminjaman</h2></div>
                        <form method="POST" action="" class="space-y-4">
                            <div><label class="text-xs font-bold text-slate-500 uppercase">Pilih Anggota</label><select name="user_id" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-orange-300 outline-none" required><option value="">-- Pilih Siswa --</option><?php while($u = mysqli_fetch_assoc($all_members)): ?><option value="<?= $u['id'] ?>"><?= $u['username'] ?></option><?php endwhile; ?></select></div>
                            <div><label class="text-xs font-bold text-slate-500 uppercase">Pilih Buku</label>
                                <select name="book_id" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-orange-300 outline-none" required>
                                    <option value="">-- Pilih Buku --</option>
                                    <?php 
                                    mysqli_data_seek($all_books_drop, 0); 
                                    while($b = mysqli_fetch_assoc($all_books_drop)): 
                                        $cek = mysqli_query($conn, "SELECT id FROM loans WHERE book_id = " . $b['id'] . " AND status='dipinjam' LIMIT 1");
                                        $is_dipinjam = (mysqli_num_rows($cek) > 0);
                                    ?>
                                        <option value="<?= $b['id'] ?>" <?= $is_dipinjam ? 'disabled' : '' ?>>
                                            <?= $b['title'] ?> <?= $is_dipinjam ? '(Sedang Dipinjam)' : '' ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" name="add_loan" class="w-full bg-orange-500 text-white font-bold py-3 rounded-xl hover:bg-orange-600 transition mt-4">Proses Peminjaman</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- SEDANG DIPINJAM -->
                <?php if($section == 'loans_active'): ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden print-content">
                        <div class="p-5 border-b border-slate-50 bg-orange-50/50 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 no-print">
                            <h2 class="font-bold text-orange-700 uppercase text-xs tracking-wider"><i class="fas fa-clock mr-2"></i> Buku Sedang Dipinjam</h2>
                            <div class="flex gap-2 w-full lg:w-auto items-center">
                                <form method="GET" class="relative w-full lg:w-48">
                                    <input type="hidden" name="section" value="loans_active">
                                    <input type="date" name="date_loans" value="<?= $date_loans ?>" onchange="this.form.submit()" class="w-full px-2 py-1.5 text-xs border border-orange-200 rounded-lg focus:ring-1 focus:ring-orange-300 outline-none">
                                </form>
                                <form method="GET" class="relative w-full lg:w-48">
                                    <input type="hidden" name="section" value="loans_active">
                                    <input type="hidden" name="date_loans" value="<?= $date_loans ?>">
                                    <input type="text" name="search_loans" value="<?= htmlspecialchars($search_loans) ?>" placeholder="Cari nama/buku..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-orange-200 rounded-lg focus:ring-1 focus:ring-orange-300 outline-none">
                                    <i class="fas fa-search absolute left-2.5 top-2 text-orange-300 text-[10px]"></i>
                                </form>
                                <button onclick="window.print()" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-black transition"><i class="fas fa-print"></i> PDF</button>
                            </div>
                        </div>
                        <div class="print-header">
                            <h1 class="text-xl font-bold uppercase mb-1">Laporan Buku Sedang Dipinjam</h1>
                            <p class="text-sm">Dicetak: <?= date('d-m-Y') ?></p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                    <tr><th class="p-4">Peminjam</th><th class="p-4">Buku</th><th class="p-4 text-center">Tgl Pinjam</th><th class="p-4 text-center">Batas Kembali</th><th class="p-4 no-print-col">Aksi</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-sm">
                                    <?php while($l = mysqli_fetch_assoc($loans)): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-bold text-slate-700"><?= $l['username'] ?></td>
                                        <td class="p-4 text-slate-600 truncate max-w-[150px]"><?= $l['title'] ?></td>
                                        <td class="p-4 text-center text-slate-500 text-xs"><?= $l['loan_date'] ?></td>
                                        <td class="p-4 text-center"><span class="bg-red-100 text-red-600 px-2 py-1 rounded-lg text-xs font-bold"><?= $l['return_date'] ?></span></td>
                                        <td class="p-4 text-center flex justify-center gap-2 no-print-col">
                                            <a href="?kembalikan=<?= $l['id'] ?>&section=loans_active" class="bg-green-500 text-white px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase hover:bg-green-600 transition shadow-sm"><i class="fas fa-check"></i> Kembali</a>
                                            <a href="?delete_loan=<?= $l['id'] ?>&section=loans_active" onclick="return confirm('Hapus?')" class="bg-red-100 text-red-500 px-2 py-1.5 rounded-xl hover:bg-red-500 hover:text-white transition"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- RIWAYAT PINJAM -->
                <?php if($section == 'loans_history'): ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden print-content">
                        <div class="p-5 border-b border-slate-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print-controls no-print">
                            <h2 class="font-bold text-slate-700 flex items-center uppercase text-xs tracking-wider"><i class="fas fa-history mr-2 text-slate-400"></i> Riwayat Pinjam</h2>
                            <div class="flex gap-2 w-full lg:w-auto">
                                <form method="GET" class="relative w-full lg:w-64 flex items-center gap-2">
                                    <input type="hidden" name="section" value="loans_history">
                                    <input type="text" name="search_history" value="<?= htmlspecialchars($search_history) ?>" placeholder="Cari nama/buku..." class="w-full pl-10 pr-3 py-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-300 outline-none bg-white">
                                    <i class="fas fa-search absolute left-4 top-2.5 text-slate-300 text-xs"></i>
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-blue-700 transition">Cari</button>
                                </form>
                                <a href="?section=loans_history" class="bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-300 transition flex items-center justify-center whitespace-nowrap"><i class="fas fa-undo mr-1"></i> Reset</a>
                                <button onclick="window.print()" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition flex items-center justify-center whitespace-nowrap"><i class="fas fa-print mr-1"></i> PDF</button>
                            </div>
                        </div>
                        <div class="print-header">
                            <h1 class="text-xl font-bold uppercase mb-1">Laporan Riwayat Peminjaman</h1>
                            <p class="text-sm">Dicetak: <?= date('d-m-Y') ?></p>
                        </div>
                        <div class="overflow-x-auto p-2">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold tracking-wider">
                                    <tr><th class="p-4">Anggota</th><th class="p-4">Buku</th><th class="p-4 text-center">Tgl Pinjam</th><th class="p-4 text-center">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 text-sm">
                                    <?php while($h = mysqli_fetch_assoc($history)): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-bold text-slate-700"><?= $h['username'] ?></td>
                                        <td class="p-4 text-slate-600 truncate max-w-[150px]"><?= $h['title'] ?></td>
                                        <td class="p-4 text-center text-slate-500 text-xs"><?= $h['loan_date'] ?></td>
                                        <td class="p-4 text-center"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-xl text-[10px] font-bold uppercase"><?= $h['status'] ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- MODAL EDIT BUKU -->
    <div id="editBookModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[100] backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Edit Buku</h3>
            <form method="POST" action="?section=books_list" class="space-y-4">
                <input type="hidden" name="book_id" id="edit_book_id">
                <input type="text" name="title" id="edit_title" class="w-full p-2 border rounded-xl text-sm" required placeholder="Judul Buku">
                <input type="text" name="author" id="edit_author" class="w-full p-2 border rounded-xl text-sm" required placeholder="Penulis">
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeEditBookModal()" class="flex-1 bg-slate-100 text-slate-600 py-2 rounded-xl text-sm font-bold hover:bg-slate-200">Batal</button>
                    <button type="submit" name="update_book" class="flex-1 bg-blue-600 text-white py-2 rounded-xl text-sm font-bold hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT MEMBER -->
    <div id="editMemberModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[100] backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Edit Anggota</h3>
            <form method="POST" action="?section=members_list" class="space-y-4">
                <input type="hidden" name="member_id" id="edit_member_id">
                <input type="text" name="username" id="edit_username" class="w-full p-2 border rounded-xl text-sm" required>
                <select name="role" id="edit_role" class="w-full p-2 border rounded-xl text-sm">
                    <option value="siswa">Siswa</option>
                    <option value="admin">Admin</option>
                </select>
                <input type="password" name="password" id="edit_password" class="w-full p-2 border rounded-xl text-sm" placeholder="Password baru (opsional)">
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-slate-100 text-slate-600 py-2 rounded-xl text-sm font-bold hover:bg-slate-200">Batal</button>
                    <button type="submit" name="update_member" class="flex-1 bg-blue-600 text-white py-2 rounded-xl text-sm font-bold hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSubmenu(id) {
            const el = document.getElementById(id);
            el.classList.toggle('hidden');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function openEditBookModal(id, title, author) {
            document.getElementById('edit_book_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('editBookModal').classList.remove('hidden');
            document.getElementById('editBookModal').classList.add('flex');
        }

        function closeEditBookModal() {
            document.getElementById('editBookModal').classList.add('hidden');
            document.getElementById('editBookModal').classList.remove('flex');
        }

        function openEditModal(member) {
            document.getElementById('edit_member_id').value = member.id;
            document.getElementById('edit_username').value = member.username;
            document.getElementById('edit_role').value = member.role;
            document.getElementById('edit_password').value = ''; 
            document.getElementById('editMemberModal').classList.remove('hidden');
            document.getElementById('editMemberModal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('editMemberModal').classList.add('hidden');
            document.getElementById('editMemberModal').classList.remove('flex');
        }
    </script>
</body>
</html>