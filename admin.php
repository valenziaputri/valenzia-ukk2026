<?php 
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM books LIKE 'created_at'");
if(mysqli_num_rows($check_col) == 0){
    mysqli_query($conn, "ALTER TABLE books ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

if (isset($_POST['update_condition'])) {
    $id = (int)$_POST['book_id'];
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $persentase = (int)$_POST['persentase'];
    mysqli_query($conn, "UPDATE books SET kondisi='$kondisi', persentase_kondisi='$persentase' WHERE id=$id");
    header("Location: admin.php?msg=Kondisi Buku Diperbarui");
    exit();
}

if (isset($_POST['add_book'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $stock = (int)$_POST['stock'];
    $kondisi = "Baik";
    $persentase = 90;

    $query = "INSERT INTO books (title, author, stock, kondisi, persentase_kondisi) VALUES ('$title', '$author', '$stock', '$kondisi', '$persentase')";
    mysqli_query($conn, $query);
    
    header("Location: admin.php?msg=Buku Berhasil Ditambahkan");
    exit();
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM loans WHERE book_id = $id");
    mysqli_query($conn, "DELETE FROM books WHERE id = $id");
    header("Location: admin.php?msg=Buku Berhasil Dihapus");
    exit();
}

if (isset($_POST['add_member'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('$username', '$hashed_password', 'siswa')");
    header("Location: admin.php?msg=Anggota Berhasil Ditambahkan");
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
    header("Location: admin.php?section=members&msg=Data Anggota Berhasil Diperbarui");
    exit();
}

if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    mysqli_query($conn, "DELETE FROM loans WHERE user_id = $id");
    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
    header("Location: admin.php?section=members&msg=Anggota Berhasil Dihapus");
    exit();
}

if (isset($_POST['add_loan'])) {
    $user_id = (int)$_POST['user_id'];
    $book_id = (int)$_POST['book_id'];
    $loan_date = date('Y-m-d');
    $return_date = date('Y-m-d', strtotime($loan_date . ' + 7 days'));

    $check_stock = mysqli_query($conn, "SELECT stock FROM books WHERE id=$book_id");
    $stock = mysqli_fetch_assoc($check_stock);
    
    if ($stock['stock'] > 0) {
        mysqli_query($conn, "INSERT INTO loans (user_id, book_id, loan_date, return_date, status) VALUES ('$user_id', '$book_id', '$loan_date', '$return_date', 'dipinjam')");
        mysqli_query($conn, "UPDATE books SET stock = stock - 1 WHERE id=$book_id");
        header("Location: admin.php?msg=Peminjaman Berhasil Ditambahkan");
    } else {
        header("Location: admin.php?msg=Gagal: Stok Buku Habis!");
    }
    exit();
}

if (isset($_GET['delete_loan'])) {
    $loan_id = (int)$_GET['delete_loan'];
    $data = mysqli_query($conn, "SELECT book_id FROM loans WHERE id=$loan_id");
    if(mysqli_num_rows($data) > 0){
        $d = mysqli_fetch_assoc($data);
        mysqli_query($conn, "UPDATE books SET stock = stock + 1 WHERE id=" . $d['book_id']);
        mysqli_query($conn, "DELETE FROM loans WHERE id=$loan_id");
    }
    header("Location: admin.php?msg=Peminjaman Berhasil Dihapus");
    exit();
}

if (isset($_GET['kembalikan'])) {
    $loan_id = (int)$_GET['kembalikan'];
    $loan_data = mysqli_query($conn, "SELECT book_id FROM loans WHERE id=$loan_id");
    if(mysqli_num_rows($loan_data) > 0){
        $loan = mysqli_fetch_assoc($loan_data);
        $book_id = $loan['book_id'];
        mysqli_query($conn, "UPDATE loans SET status='kembali' WHERE id=$loan_id");
        mysqli_query($conn, "UPDATE books SET stock = stock + 1 WHERE id=$book_id");
    }
    header("Location: admin.php?msg=Buku Telah Dikembalikan");
    exit();
}

 $search_books = isset($_GET['search_books']) ? mysqli_real_escape_string($conn, $_GET['search_books']) : '';
 $query_books = "SELECT * FROM books";
if ($search_books) $query_books .= " WHERE title LIKE '%$search_books%' OR author LIKE '%$search_books%'";
 $query_books .= " ORDER BY id DESC";
 $books = mysqli_query($conn, $query_books);

 $search_loans = isset($_GET['search_loans']) ? mysqli_real_escape_string($conn, $_GET['search_loans']) : '';
 $query_loans = "SELECT loans.*, users.username, books.title
             FROM loans JOIN users ON loans.user_id = users.id
             JOIN books ON loans.book_id = books.id
             WHERE status = 'dipinjam'";
if ($search_loans) $query_loans .= " AND (users.username LIKE '%$search_loans%' OR books.title LIKE '%$search_loans%')";
 $query_loans .= " ORDER BY loan_date ASC";
 $loans = mysqli_query($conn, $query_loans);

 $search_history = isset($_GET['search_history']) ? mysqli_real_escape_string($conn, $_GET['search_history']) : '';
 $query_history = "SELECT loans.*, users.username, books.title
               FROM loans JOIN users ON loans.user_id = users.id
               JOIN books ON loans.book_id = books.id
               WHERE status != 'dipinjam'";
if ($search_history) $query_history .= " AND (users.username LIKE '%$search_history%' OR books.title LIKE '%$search_history%')";
 $query_history .= " ORDER BY loan_date DESC";
 $history = mysqli_query($conn, $query_history);

 $search_members = isset($_GET['search_members']) ? mysqli_real_escape_string($conn, $_GET['search_members']) : '';
 $query_members = "SELECT * FROM users WHERE role='siswa'";
if ($search_members) $query_members .= " AND username LIKE '%$search_members%'";
 $query_members .= " ORDER BY id DESC";
 $members = mysqli_query($conn, $query_members);

 $search_condition = isset($_GET['search_condition']) ? mysqli_real_escape_string($conn, $_GET['search_condition']) : '';
 $query_condition = "SELECT * FROM books";
if ($search_condition) {
    $query_condition .= " WHERE title LIKE '%$search_condition%' OR author LIKE '%$search_condition%' OR kondisi LIKE '%$search_condition%'";
}
 $query_condition .= " ORDER BY id DESC";
 $condition_books = mysqli_query($conn, $query_condition);

 $all_members = mysqli_query($conn, "SELECT * FROM users WHERE role='siswa' ORDER BY username ASC");
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
        /* --- STYLE TAMPILAN WEBSITE --- */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* --- STYLE KHUSUS PRINT (PDF) --- */
        @media print {
            /* 1. Sembunyikan elemen UI (Sidebar, Nav, Form) */
            .no-print, nav, .no-print * { 
                display: none !important; 
            }
            
            /* 2. Default: Sembunyikan SEMUA bagian printable section */
            .print-section {
                display: none !important;
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* 3. Tampilkan HANYA bagian yang aktif (sedang diklik) */
            .print-active {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                padding: 20px !important; /* Padding kertas */
                z-index: 9999;
            }
            
            /* 4. Reset Layout Grid jadi Block lebar penuh */
            main > div > div { 
                display: block !important; 
            }
            
            /* 5. Paksa Main Content ambil lebar kertas */
            .lg\:col-span-8 {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100%;
                padding: 0 !important;
            }

            /* 6. Styling Tabel */
            table { 
                width: 100% !important; 
                border-collapse: collapse !important; 
                margin-bottom: 20px !important;
            }

            th, td { 
                border: 1px solid #000 !important; 
                padding: 10px !important; 
                text-align: left !important; 
                font-size: 12px !important;
                color: #000 !important;
            }

            th { 
                background-color: #f3f4f6 !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact; /* Agar background warna tercetak */
            }

            /* 7. Styling Header Print */
            .print-header {
                display: none !important; /* Default hidden */
                text-align: center;
                margin-bottom: 20px !important;
            }
            .print-header.active {
                display: block !important; /* Muncul jika aktif */
            }

            /* 8. Pastikan warna teks hitam */
            body, h1, h2, p, span, div {
                color: #000 !important;
                font-family: sans-serif !important;
            }
            
            .text-slate-400, .text-slate-500, .text-slate-600, .text-slate-700, .text-orange-600, .text-blue-600 {
                color: #000 !important;
            }

            /* 9. Sembunyikan kolom 'Aksi' saat print */
            .no-print-table {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800">

    <!-- NAVIGATION -->
    <nav class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-8 py-4 flex justify-between items-center shadow-lg sticky top-0 z-50 no-print">
        <div class="flex items-center gap-3">
            <div class="bg-white/20 p-2 rounded-lg text-white backdrop-blur-sm">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="text-xl font-bold tracking-tight">PerpusSmecha</h1>
        </div>
        <div class="flex items-center gap-6">
            <span class="text-sm hidden sm:block">Halo, <b><?= $_SESSION['username'] ?? 'Administrator' ?></b></span>
            <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin logout?');" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-bold transition shadow-lg shadow-red-500/30">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
        </div>
    </nav>

    <!-- FLASH MESSAGE -->
    <?php if(isset($_GET['msg'])): ?>
        <div class="max-w-7xl mx-auto mt-6 px-4 no-print">
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl shadow-sm flex items-center justify-between">
                <span><i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['msg']) ?></span>
                <a href="admin.php" class="text-green-800 hover:underline font-bold text-xs uppercase ml-4">Tutup</a>
            </div>
        </div>
    <?php endif; ?>

    <main class="p-4 lg:p-6 max-w-[1600px] mx-auto space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- SIDEBAR (Kiri - Hilang saat print) -->
            <div class="lg:col-span-4 space-y-6 no-print">
                
                <!-- 1. Tambah Buku -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center"><i class="fas fa-book-medical mr-2 text-blue-500"></i> Tambah Buku</h2>
                    <form method="POST" action="" class="space-y-4">
                        <input type="text" name="title" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none text-sm" placeholder="Judul Buku" required>
                        <input type="text" name="author" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none text-sm" placeholder="Penulis" required>
                        <input type="number" name="stock" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none text-sm" placeholder="Stok Awal" required>
                        <button type="submit" name="add_book" class="w-full bg-blue-600 text-white font-bold py-3 rounded-2xl hover:bg-blue-700 transition shadow-xl shadow-blue-100">Simpan Buku</button>
                    </form>
                </div>

                <!-- 2. Tambah Anggota -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center"><i class="fas fa-user-plus mr-2 text-purple-500"></i> Daftar Anggota</h2>
                    <form method="POST" action="" class="space-y-4">
                        <input type="text" name="username" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-purple-500 outline-none text-sm" placeholder="Username Siswa" required>
                        <input type="password" name="password" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-purple-500 outline-none text-sm" placeholder="Password" required>
                        <button type="submit" name="add_member" class="w-full bg-purple-600 text-white font-bold py-3 rounded-2xl hover:bg-purple-700 transition shadow-xl shadow-purple-100">Tambah Anggota</button>
                    </form>
                </div>

                <!-- 3. Input Peminjaman -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                    <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center"><i class="fas fa-hand-holding-heart mr-2 text-orange-500"></i> Input Peminjaman</h2>
                    <form method="POST" action="" class="space-y-4">
                        <select name="user_id" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none text-sm" required>
                            <option value="">-- Pilih Anggota --</option>
                            <?php while($u = mysqli_fetch_assoc($all_members)): ?>
                                <option value="<?= $u['id'] ?>"><?= $u['username'] ?></option>
                            <?php endwhile; ?>
                            <?php mysqli_data_seek($all_members, 0); ?>
                        </select>
                        <select name="book_id" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none text-sm" required>
                            <option value="">-- Pilih Buku --</option>
                            <?php 
                            $books_drop = mysqli_query($conn, "SELECT * FROM books ORDER BY title ASC");
                            while($b = mysqli_fetch_assoc($books_drop)): ?>
                                <option value="<?= $b['id'] ?>" <?= $b['stock'] <= 0 ? 'disabled class="text-gray-400"' : '' ?>>
                                    <?= $b['title'] ?> (Stok: <?= $b['stock'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="add_loan" class="w-full bg-orange-500 text-white font-bold py-3 rounded-2xl hover:bg-orange-600 transition shadow-xl shadow-orange-100">Proses Peminjaman</button>
                    </form>
                </div>
            </div>

            <!-- MAIN CONTENT (Kanan - Tetap Ada saat print) -->
            <div class="lg:col-span-8 space-y-8">
                
                <!-- 1. PINJAMAN BERLANGSUNG (Print Area) -->
                <div id="printable-loans-area" class="print-section bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    
                    <!-- Kontrol Layar (No Print) -->
                    <div class="p-5 border-b border-slate-50 bg-orange-50/50 flex flex-col sm:flex-row justify-between items-center gap-4 no-print">
                        <div>
                            <h2 class="font-bold text-orange-700 uppercase text-xs tracking-wider"><i class="fas fa-hourglass-half mr-2"></i> Sedang Dipinjam</h2>
                            <p class="text-[10px] text-slate-400 mt-1">Cetak laporan peminjaman ini saja</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto items-end sm:items-center">
                            <div class="flex items-center gap-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Filter:</label>
                                <select id="loansDateFilter" onchange="filterLoans()" class="p-2 text-xs border border-orange-200 rounded-xl focus:ring-1 focus:ring-orange-300 outline-none bg-white">
                                    <option value="all">Semua Waktu</option>
                                    <option value="today">Hari Ini (<?= date('d-m-Y') ?>)</option>
                                </select>
                            </div>
                            <button onclick="printSection('printable-loans-area')" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition flex items-center justify-center gap-2 shadow-lg shadow-slate-200 whitespace-nowrap">
                                <i class="fas fa-print"></i> Cetak PDF
                            </button>
                            <form method="GET" class="relative w-full sm:w-48">
                                <input type="text" name="search_loans" value="<?= htmlspecialchars($search_loans) ?>" placeholder="Cari data..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-orange-200 rounded-lg focus:ring-1 focus:ring-orange-300 outline-none">
                                <i class="fas fa-search absolute left-3 top-2 text-orange-300 text-[10px]"></i>
                            </form>
                        </div>
                    </div>

                    <!-- Header Print (Muncul saat Print) -->
                    <div class="print-header">
                        <h1 class="text-2xl font-bold uppercase tracking-widest border-b-2 border-black pb-2 inline-block">Laporan Peminjaman Buku</h1>
                        <p class="text-sm mt-2 font-semibold">Perpustakaan Sekolah</p>
                        <p class="text-xs mt-1">Dicetak oleh: <?= $_SESSION['username'] ?> pada tanggal: <span id="printDateLoans"></span></p>
                    </div>

                    <!-- Tabel -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left" id="loansTable">
                            <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                <tr>
                                    <th class="p-4 border-b border-slate-200">No</th>
                                    <th class="p-4 border-b border-slate-200">Nama Peminjam</th>
                                    <th class="p-4 border-b border-slate-200">Judul Buku</th>
                                    <th class="p-4 border-b border-slate-200 text-center">Tanggal Pinjam</th>
                                    <th class="p-4 border-b border-slate-200 text-center">Batas Kembali</th>
                                    <th class="p-4 border-b border-slate-200 text-center no-print-table">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-sm">
                                <?php 
                                $no = 1;
                                if(mysqli_num_rows($loans) > 0): 
                                    while($l = mysqli_fetch_assoc($loans)): 
                                        $loan_date_attr = substr($l['loan_date'], 0, 10);
                                ?>
                                    <tr class="hover:bg-slate-50 transition loan-row" data-date="<?= $loan_date_attr ?>">
                                        <td class="p-4 text-slate-400 font-mono text-xs"><?= $no++ ?></td>
                                        <td class="p-4 font-bold text-slate-700"><?= $l['username'] ?></td>
                                        <td class="p-4 text-slate-600 truncate max-w-[150px]"><?= $l['title'] ?></td>
                                        <td class="p-4 text-center text-slate-500 text-xs"><?= $l['loan_date'] ?></td>
                                        <td class="p-4 text-center"><span class="bg-red-100 text-red-600 px-2 py-1 rounded text-xs font-bold"><?= $l['return_date'] ?></span></td>
                                        <td class="p-4 text-center no-print-table">
                                            <div class="flex justify-center gap-2">
                                                <a href="?kembalikan=<?= $l['id'] ?>&search_loans=<?= $search_loans ?>" class="bg-green-500 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase hover:bg-green-600 transition shadow-sm"><i class="fas fa-check"></i> Kembali</a>
                                                <a href="?delete_loan=<?= $l['id'] ?>&search_loans=<?= $search_loans ?>" onclick="return confirm('Hapus?')" class="bg-red-100 text-red-500 px-2 py-1.5 rounded-lg hover:bg-red-500 hover:text-white transition"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr class="loan-row"><td colspan="6" class="p-6 text-center text-slate-400 text-xs">Tidak ada peminjaman aktif.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Footer Print -->
                    <div class="hidden print:flex justify-between mt-8 pt-8 text-xs">
                        <div class="text-center">
                            <p class="mb-16">Mengetahui,</p>
                            <p class="font-bold border-b border-black inline-block w-32 pb-1">Kepala Sekolah</p>
                        </div>
                        <div class="text-center">
                            <p class="mb-16"><?= date('d-m-Y') ?></p>
                            <p class="font-bold border-b border-black inline-block w-32 pb-1">Petugas Perpus</p>
                        </div>
                    </div>
                </div>

                <!-- 2. RIWAYAT PINJAMAN (Print Area) -->
                <div id="printable-area" class="print-section bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    
                    <!-- Kontrol Layar -->
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 no-print">
                        <div>
                            <h2 class="font-bold text-slate-700 flex items-center uppercase text-xs tracking-wider"><i class="fas fa-history mr-2 text-slate-400"></i> Riwayat Pinjaman</h2>
                            <p class="text-[10px] text-slate-400 mt-1">Cetak laporan riwayat ini saja</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                            <select id="filterMember" onchange="filterHistory()" class="w-full sm:w-48 p-2 text-xs border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-300 outline-none">
                                <option value="">-- Semua Anggota --</option>
                                <?php 
                                mysqli_data_seek($all_members, 0);
                                while($u = mysqli_fetch_assoc($all_members)): ?>
                                    <option value="<?= $u['username'] ?>"><?= $u['username'] ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button onclick="printSection('printable-area')" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition flex items-center justify-center gap-2 shadow-lg shadow-slate-200">
                                <i class="fas fa-print"></i> Cetak PDF
                            </button>
                        </div>
                    </div>

                    <!-- Header Print -->
                    <div class="print-header">
                        <h1 class="text-2xl font-bold uppercase tracking-widest border-b-2 border-black pb-2 inline-block">Laporan Riwayat Peminjaman</h1>
                        <p class="text-sm mt-2 font-semibold">Perpustakaan Sekolah</p>
                        <p class="text-xs mt-1">Dicetak oleh: <?= $_SESSION['username'] ?> pada tanggal: <span id="printDate"></span></p>
                    </div>

                    <div class="overflow-x-auto p-2">
                        <table class="w-full text-left history-table" id="historyTable">
                            <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="p-4 border-b border-slate-200">No</th>
                                    <th class="p-4 border-b border-slate-200">Nama Anggota</th>
                                    <th class="p-4 border-b border-slate-200">Judul Buku</th>
                                    <th class="p-4 border-b border-slate-200 text-center">Tgl Pinjam</th>
                                    <th class="p-4 border-b border-slate-200 text-center">Tgl Kembali</th>
                                    <th class="p-4 border-b border-slate-200 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 text-sm" id="historyBody">
                                <?php 
                                mysqli_data_seek($history, 0); 
                                $no = 1;
                                if(mysqli_num_rows($history) > 0): 
                                    while($h = mysqli_fetch_assoc($history)): 
                                ?>
                                    <tr class="hover:bg-slate-50 transition history-row" data-user="<?= $h['username'] ?>">
                                        <td class="p-4 text-slate-400 font-mono text-xs"><?= $no++ ?></td>
                                        <td class="p-4 font-bold text-slate-700"><?= $h['username'] ?></td>
                                        <td class="p-4 text-slate-600 truncate max-w-[150px]"><?= $h['title'] ?></td>
                                        <td class="p-4 text-center text-slate-500 text-xs"><?= $h['loan_date'] ?></td>
                                        <td class="p-4 text-center text-slate-500 text-xs"><?= $h['return_date'] ?></td>
                                        <td class="p-4 text-center">
                                            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-bold uppercase">
                                                <?= $h['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr class="history-row"><td colspan="6" class="p-8 text-center text-slate-400 text-sm">Belum ada riwayat peminjaman.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Footer Print -->
                        <div class="hidden print:flex justify-between mt-8 pt-8 text-xs">
                            <div class="text-center">
                                <p class="mb-16">Mengetahui,</p>
                                <p class="font-bold border-b border-black inline-block w-32 pb-1">Kepala Sekolah</p>
                            </div>
                            <div class="text-center">
                                <p class="mb-16"><?= date('d-m-Y') ?></p>
                                <p class="font-bold border-b border-black inline-block w-32 pb-1">Petugas Perpus</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. KATALOG BUKU (Print Area) -->
                <div id="printable-books-area" class="print-section bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    
                    <!-- Kontrol Layar -->
                    <div class="p-5 border-b border-slate-50 bg-blue-50/50 flex flex-col sm:flex-row justify-between items-center gap-4 no-print">
                        <h2 class="font-bold text-blue-700 uppercase text-xs tracking-wider"><i class="fas fa-book mr-2"></i> Katalog Data Buku</h2>
                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto items-end sm:items-center">
                            <div class="flex items-center gap-2">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Filter:</label>
                                <select id="dateFilter" onchange="filterBooks()" class="p-2 text-xs border border-blue-200 rounded-xl focus:ring-1 focus:ring-blue-300 outline-none bg-white">
                                    <option value="all">Semua Waktu</option>
                                    <option value="today">Baru Hari Ini (<?= date('d-m-Y') ?>)</option>
                                </select>
                            </div>
                            <button onclick="printSection('printable-books-area')" class="bg-slate-800 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition flex items-center justify-center gap-2 shadow-lg shadow-slate-200 whitespace-nowrap">
                                <i class="fas fa-print"></i> Cetak PDF
                            </button>
                            <form method="GET" class="relative w-full sm:w-48">
                                <input type="text" name="search_books" value="<?= htmlspecialchars($search_books) ?>" placeholder="Cari buku..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-blue-200 rounded-lg focus:ring-1 focus:ring-blue-300 outline-none">
                                <i class="fas fa-search absolute left-3 top-2 text-blue-300 text-[10px]"></i>
                            </form>
                        </div>
                    </div>

                    <!-- Header Print -->
                    <div class="print-header">
                        <h1 class="text-2xl font-bold uppercase tracking-widest border-b-2 border-black pb-2 inline-block">Laporan Katalog Buku</h1>
                        <p class="text-sm mt-2 font-semibold">Perpustakaan Sekolah</p>
                        <p class="text-xs mt-1">Dicetak oleh: <?= $_SESSION['username'] ?> pada tanggal: <span id="printDateBooks"></span></p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" id="booksTable">
                            <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                <tr>
                                    <th class="p-4 border-b border-slate-200">Judul Buku</th>
                                    <th class="p-4 border-b border-slate-200">Penulis</th>
                                    <th class="p-4 border-b border-slate-200">Stok</th>
                                    <th class="p-4 border-b border-slate-200">Kondisi</th>
                                    <th class="p-4 border-b border-slate-200 no-print-table">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php 
                                mysqli_data_seek($books, 0);
                                while($row = mysqli_fetch_assoc($books)): 
                                    $created_at = isset($row['created_at']) ? substr($row['created_at'], 0, 10) : '';
                                ?>
                                <tr class="hover:bg-slate-50 transition book-row" data-date="<?= $created_at ?>">
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800 text-sm"><?= $row['title'] ?></div>
                                        <?php if($created_at): ?>
                                            <div class="text-[10px] text-slate-400"><i class="far fa-clock"></i> <?= date('d/m/Y', strtotime($created_at)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-slate-600"><?= $row['author'] ?></td>
                                    <td class="p-4 text-center">
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-[10px] font-bold"><?= $row['stock'] ?></span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] uppercase">
                                            <?= $row['kondisi'] ?? 'Baik' ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center no-print-table">
                                        <a href="?delete=<?= $row['id'] ?>&search_books=<?= $search_books ?>" onclick="return confirm('Hapus buku ini?')" class="text-red-400 hover:text-red-600 p-2 rounded-lg hover:bg-red-50 transition"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Print -->
                    <div class="hidden print:flex justify-between mt-8 pt-8 text-xs">
                        <div class="text-center">
                            <p class="mb-16">Mengetahui,</p>
                            <p class="font-bold border-b border-black inline-block w-32 pb-1">Kepala Sekolah</p>
                        </div>
                        <div class="text-center">
                            <p class="mb-16"><?= date('d-m-Y') ?></p>
                            <p class="font-bold border-b border-black inline-block w-32 pb-1">Petugas Perpus</p>
                        </div>
                    </div>
                </div>

                <!-- 4. KONDISI BUKU (No Print Only) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden no-print">
                    <div class="p-5 border-b border-slate-50 bg-teal-50/50 flex justify-between items-center">
                        <h2 class="font-bold text-teal-700 uppercase text-xs tracking-wider"><i class="fas fa-heartbeat mr-2"></i> Kondisi Buku</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                <tr>
                                    <th class="p-4">Buku</th>
                                    <th class="p-4 text-center">Kondisi</th>
                                    <th class="p-4 text-center w-1/3">Health</th>
                                    <th class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php 
                                mysqli_data_seek($condition_books, 0);
                                while($row = mysqli_fetch_assoc($condition_books)): 
                                    $kondisi = $row['kondisi'] ?? 'Baik';
                                    $persentase = (int)($row['persentase_kondisi'] ?? 90);
                                    $bar_color = $persentase >= 80 ? 'bg-green-500' : ($persentase >= 50 ? 'bg-yellow-400' : 'bg-red-500');
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-4 text-slate-800 font-bold text-sm"><?= $row['title'] ?></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase 
                                            <?= $persentase >= 80 ? 'bg-green-100 text-green-700' : ($persentase >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                                            <?= $kondisi ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center align-middle">
                                        <div class="w-full bg-slate-200 rounded-full h-2 mb-1">
                                            <div class="<?= $bar_color ?> h-2 rounded-full" style="width: <?= $persentase ?>%"></div>
                                        </div>
                                        <span class="text-[10px] text-slate-500 font-bold"><?= $persentase ?>%</span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <button onclick="openConditionModal(<?= $row['id'] ?>, '<?= $kondisi ?>', <?= $persentase ?>, '<?= $row['title'] ?>')" class="text-teal-600 hover:bg-teal-50 px-2 py-1 rounded text-xs font-bold transition">
                                            <i class="fas fa-sync-alt"></i> Update
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 5. KELOLA ANGGOTA (No Print Only) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden no-print">
                    <div class="p-5 border-b border-slate-50 bg-purple-50/50 flex justify-between items-center">
                        <h2 class="font-bold text-purple-700 uppercase text-xs tracking-wider"><i class="fas fa-users mr-2"></i> Kelola Anggota</h2>
                        <form method="GET" class="relative w-48">
                            <input type="text" name="search_members" value="<?= htmlspecialchars($search_members) ?>" placeholder="Cari username..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-purple-200 rounded-lg focus:ring-1 focus:ring-purple-300 outline-none">
                            <i class="fas fa-search absolute left-3 top-2 text-purple-300 text-[10px]"></i>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold">
                                <tr>
                                    <th class="p-4">Username</th>
                                    <th class="p-4">Role</th>
                                    <th class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php 
                                mysqli_data_seek($members, 0);
                                while($m = mysqli_fetch_assoc($members)): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-4 font-bold text-slate-700 flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-[10px]"><i class="fas fa-user"></i></div>
                                        <?= $m['username'] ?>
                                    </td>
                                    <td class="p-4"><span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] uppercase"><?= $m['role'] ?></span></td>
                                    <td class="p-4 text-center flex justify-center gap-2">
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($m)) ?>)" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white w-7 h-7 rounded-lg flex items-center justify-center transition"><i class="fas fa-edit text-xs"></i></button>
                                        <a href="?delete_user=<?= $m['id'] ?>&search_members=<?= $search_members ?>" onclick="return confirm('Hapus?')" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white w-7 h-7 rounded-lg flex items-center justify-center transition"><i class="fas fa-trash text-xs"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL UPDATE KONDISI -->
    <div id="conditionModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[100] backdrop-blur-sm no-print">
        <div class="bg-white rounded-3xl shadow-2xl p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Update Kondisi</h3>
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="book_id" id="cond_book_id">
                <div class="text-sm font-bold text-slate-600 mb-2" id="cond_book_title">...</div>
                <select name="kondisi" id="cond_status" class="w-full p-2 border rounded-xl text-sm">
                    <option value="Baru">Baru</option>
                    <option value="Baik">Baik</option>
                    <option value="Rusak Ringan">Rusak Ringan</option>
                    <option value="Rusak Berat">Rusak Berat</option>
                </select>
                <div>
                    <label class="text-xs font-bold text-slate-400">Persentase: <span id="cond_perc_val" class="text-teal-600">90%</span></label>
                    <input type="range" name="persentase" id="cond_perc" min="0" max="100" value="90" oninput="document.getElementById('cond_perc_val').innerText = this.value + '%'" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-teal-500">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeConditionModal()" class="flex-1 bg-slate-100 text-slate-600 py-2 rounded-xl text-sm font-bold hover:bg-slate-200">Batal</button>
                    <button type="submit" name="update_condition" class="flex-1 bg-teal-600 text-white py-2 rounded-xl text-sm font-bold hover:bg-teal-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT MEMBER -->
    <div id="editMemberModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[100] backdrop-blur-sm no-print">
        <div class="bg-white rounded-3xl shadow-2xl p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Edit Anggota</h3>
            <form method="POST" action="" class="space-y-4">
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
        // Set Tanggal Cetak Otomatis
        const fullDate = new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const elDateHistory = document.getElementById('printDate');
        const elDateLoans = document.getElementById('printDateLoans');
        const elDateBooks = document.getElementById('printDateBooks');
        
        if(elDateHistory) elDateHistory.innerText = fullDate;
        if(elDateLoans) elDateLoans.innerText = fullDate;
        if(elDateBooks) elDateBooks.innerText = fullDate;

        // --- FUNGSI CETAK TERPISAH ---
        function printSection(sectionId) {
            // 1. Hapus class active dari semua section
            const sections = document.querySelectorAll('.print-section');
            sections.forEach(sec => {
                sec.classList.remove('print-active');
            });

            // 2. Tambah class active ke section yang dipilih
            const target = document.getElementById(sectionId);
            if(target) {
                target.classList.add('print-active');
                
                // Pastikan header print di dalamnya muncul
                const header = target.querySelector('.print-header');
                if(header) header.classList.add('active');
            }

            // 3. Eksekusi Print
            window.print();
        }

        // --- FUNGSI FILTER RIWAYAT (PER ANGGOTA) ---
        function filterHistory() {
            const selectedUser = document.getElementById('filterMember').value;
            const rows = document.querySelectorAll('.history-row');

            rows.forEach(row => {
                const userCell = row.getAttribute('data-user');
                if (selectedUser === "" || selectedUser === userCell) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        // --- FUNGSI FILTER BUKU (HARI INI) ---
        function filterBooks() {
            const filterType = document.getElementById('dateFilter').value;
            const rows = document.querySelectorAll('#booksTable .book-row');
            const today = new Date().toISOString().slice(0, 10);

            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                if (filterType === 'all' || (filterType === 'today' && rowDate === today)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // --- FUNGSI FILTER PINJAMAN (HARI INI) ---
        function filterLoans() {
            const filterType = document.getElementById('loansDateFilter').value;
            const rows = document.querySelectorAll('#loansTable .loan-row');
            const today = new Date().toISOString().slice(0, 10);

            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                if (filterType === 'all' || (filterType === 'today' && rowDate === today)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // --- FUNGSI MODAL ---
        function openConditionModal(id, kondisi, persentase, title) {
            document.getElementById('cond_book_id').value = id;
            document.getElementById('cond_book_title').innerText = title;
            document.getElementById('cond_status').value = kondisi;
            document.getElementById('cond_perc').value = persentase;
            document.getElementById('cond_perc_val').innerText = persentase + '%';
            const modal = document.getElementById('conditionModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeConditionModal() {
            const modal = document.getElementById('conditionModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openEditModal(member) {
            document.getElementById('edit_member_id').value = member.id;
            document.getElementById('edit_username').value = member.username;
            document.getElementById('edit_role').value = member.role;
            document.getElementById('edit_password').value = ''; 
            const modal = document.getElementById('editMemberModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditModal() {
            const modal = document.getElementById('editMemberModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>