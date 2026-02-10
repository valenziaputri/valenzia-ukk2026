<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
 $user_id = $_SESSION['user_id'];

// --- PROSES PEMINJAMAN (LOGIKA BARU: TANPA CEK STOK, CEK STATUS LOANS) ---
if (isset($_GET['pinjam'])) {
    $book_id = (int)$_GET['pinjam'];
    $loan_date = date('Y-m-d');
    $return_date = date('Y-m-d', strtotime('+7 days'));
    
    // Cek apakah buku SEDANG DIPINJAM (oleh siapapun)
    $check_loan = mysqli_query($conn, "SELECT id FROM loans WHERE book_id = '$book_id' AND status = 'dipinjam' LIMIT 1");
    
    if ($check_loan && mysqli_num_rows($check_loan) == 0) {
        // Jika tidak ada yang meminjam, proses peminjaman
        $insert = mysqli_query($conn, "INSERT INTO loans (user_id, book_id, loan_date, return_date, status) VALUES ('$user_id', '$book_id', '$loan_date', '$return_date', 'dipinjam')");
        
        // Tidak perlu mengurangi stock
        
        if ($insert) {
            header("Location: index.php?msg=Buku berhasil dipinjam!");
        } else {
            header("Location: index.php?msg=Gagal memproses peminjaman.");
        }
    } else {
        header("Location: index.php?msg=Maaf, buku sedang dipinjam orang lain!");
    }
    exit();
}

// --- PROSES PENGEMBALIAN (TANPA MENGEMBALIKAN STOCK) ---
if (isset($_GET['kembali'])) {
    $loan_id = $_GET['kembali'];
    $qLoan = mysqli_query($conn, "SELECT * FROM loans WHERE id = '$loan_id' AND user_id = '$user_id' AND status = 'dipinjam'");
    
    if ($qLoan && mysqli_num_rows($qLoan) > 0) {
        // Update status saja, tidak perlu update stock
        $upLoan = mysqli_query($conn, "UPDATE loans SET status = 'kembali' WHERE id = '$loan_id'");
        
        if ($upLoan) {
            header("Location: index.php?msg=Buku berhasil dikembalikan!");
        } else {
            header("Location: index.php?msg=Gagal update database.");
        }
    } else {
        header("Location: index.php?msg=Data peminjaman tidak valid.");
    }
    exit();
}

// --- LOGIKA PENCARIAN KATALOG BUKU ---
 $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query dasar
 $query = "SELECT * FROM books WHERE 1=1";

// Logika Pencarian (Judul/Penulis) saja
if (!empty($search)) {
    $query .= " AND (title LIKE '%$search%' OR author LIKE '%$search%')";
}

 $query .= " ORDER BY id DESC";
 $books = mysqli_query($conn, $query);

// --- LOGIKA PENCARIAN UNTUK PEMINJAMAN & RIWAYAT ---
 $search_active = isset($_GET['search_active']) ? mysqli_real_escape_string($conn, $_GET['search_active']) : '';
 $search_history = isset($_GET['search_history']) ? mysqli_real_escape_string($conn, $_GET['search_history']) : '';

// 1. Peminjaman Aktif
 $loanQuery = "SELECT l.*, b.title 
             FROM loans l 
             JOIN books b ON l.book_id = b.id 
             WHERE l.user_id = '$user_id' AND l.status = 'dipinjam'";
if (!empty($search_active)) {
    $loanQuery .= " AND b.title LIKE '%$search_active%'";
}
 $loanQuery .= " ORDER BY l.loan_date DESC";
 $myLoans = mysqli_query($conn, $loanQuery);

// 2. Riwayat Peminjaman
 $historyQuery = "SELECT l.*, b.title 
               FROM loans l 
               JOIN books b ON l.book_id = b.id 
               WHERE l.user_id = '$user_id' AND l.status = 'kembali'";
if (!empty($search_history)) {
    $historyQuery .= " AND b.title LIKE '%$search_history%'";
}
 $historyQuery .= " ORDER BY l.loan_date DESC";
 $myHistory = mysqli_query($conn, $historyQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Dashboard Siswa - PerpusSmecha</title>
    <style>
        /* CSS KHUSUS UNTUK PRINT */
        @media print {
            body * {
                visibility: hidden;
            }
            #printable-history, #printable-history * {
                visibility: visible;
            }
            #printable-history {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                padding: 20px;
                z-index: 9999;
            }
            .no-print {
                display: none !important;
            }
            table {
                border: 1px solid #000;
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                color: black !important;
            }
            .print-only-title {
                display: block !important;
                text-align: center;
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 20px;
            }
        }
        .print-only-title {
            display: none;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="bg-gradient-to-r from-blue-400 to-blue-500 text-white px-8 py-4 flex justify-between items-center shadow-lg sticky top-0 z-50 no-print">
        <div class="flex items-center via gap-2">
            <i class="fas fa-book-reader text-2xl"></i>
            <h1 class="text-2xl font-bold italic tracking-tighter">PerpusSmecha</h1>
        </div>
        <div class="flex items-center gap-6">
            <a href="index.php" class="font-bold border-b-2 border-white pb-1">Katalog</a>
            <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin logout?');" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-bold transition shadow-lg shadow-red-500/30">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
        </div>
    </nav>

    <div class="p-8 max-w-7xl mx-auto">
        <!-- Notifikasi -->
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-xl shadow-sm flex items-center justify-between fade-in">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-xl"></i> 
                    <span class="font-bold text-sm md:text-base"><?= htmlspecialchars($_GET['msg']) ?></span>
                </div>
        
                <button onclick="window.location.href = 'index.php'" class="bg-green-100 text-green-800 hover:bg-green-200 px-4 py-2 rounded-lg text-xs md:text-sm font-bold uppercase shadow-sm transition">
                    <i class="fas fa-check mr-1"></i> Tutup
                </button>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-800">Selamat Datang Di Perpustakaan</h2>
                <p class="text-gray-500">Halo <span class="text-blue-600 font-bold"><?= $_SESSION['username'] ?? 'Siswa' ?></span>, temukan bukumu!</p>
            </div>
            
            <form action="" method="GET" class="relative w-full md:w-80 no-print">
                <input type="text" name="search" value="<?= $search ?>" placeholder="Cari judul atau penulis..." 
                       class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none shadow-sm">
                <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                <!-- Input hidden agar state pencarian lain tidak hilang -->
                <input type="hidden" name="search_active" value="<?= $search_active ?>">
                <input type="hidden" name="search_history" value="<?= $search_history ?>">
            </form>
        </div>

        <!-- BAGIAN FILTER KONDISI SUDAH DIHAPUS -->

        <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2"><i class="fas fa-layer-group mr-2"></i>Daftar Buku</h3>
        
        <!-- GRID CARD BUKU (PERBAIKAN: TANPA STOK & KONDISI FISIK) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12 no-print">
            <?php if($books && mysqli_num_rows($books) > 0): ?>
                <?php while($b = mysqli_fetch_assoc($books)): 
                    // CEK STATUS KETERSEDIAAN REAL-TIME
                    $check_avail = mysqli_query($conn, "SELECT id, user_id FROM loans WHERE book_id = " . $b['id'] . " AND status='dipinjam' LIMIT 1");
                    $loan_data = ($check_avail && mysqli_num_rows($check_avail) > 0) ? mysqli_fetch_assoc($check_avail) : null;
                    
                    $is_borrowed_by_anyone = ($loan_data !== null);
                    $is_borrowed_by_me = ($loan_data && $loan_data['user_id'] == $user_id);
                ?>
                <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all border border-gray-100 flex flex-col h-full justify-between group">
                    <div>
                        <div class="mb-3 flex justify-between items-start">
                            <!-- Hapus Badge Stok & Ikon Kondisi Fisik -->
                            <!-- Placeholder Icon Buku -->
                            <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 shadow-sm">
                                <i class="fas fa-book text-lg"></i>
                            </div>
                        </div>
                        
                        <h3 class="font-bold text-lg text-gray-800 mb-2 leading-snug line-clamp-2">
                            <?= $b['title'] ?>
                        </h3>
                        
                        <p class="text-sm text-gray-500 flex items-center gap-2 mb-4">
                            <i class="fas fa-pen-nib text-xs"></i> <?= $b['author'] ?>
                        </p>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <?php if($is_borrowed_by_me): ?>
                            <!-- Saya yang meminjam -->
                            <button disabled class="w-full bg-orange-100 text-orange-600 py-2.5 rounded-xl text-sm font-bold cursor-default border border-orange-200">
                                <i class="fas fa-clock mr-1"></i> Sedang Anda Pinjam
                            </button>
                        <?php elseif($is_borrowed_by_anyone): ?>
                            <!-- Orang lain yang meminjam -->
                            <button disabled class="w-full bg-gray-100 text-gray-400 py-2.5 rounded-xl text-sm font-bold cursor-not-allowed border border-gray-200">
                                <i class="fas fa-lock mr-1"></i> Sedang Dipinjam
                            </button>
                        <?php else: ?>
                            <!-- Tersedia -->
                            <a href="?pinjam=<?= $b['id'] ?>&search=<?= $search ?>&search_active=<?= $search_active ?>&search_history=<?= $search_history ?>" 
                               onclick="return confirm('Apakah Anda yakin ingin meminjam buku \'<?= addslashes($b['title']) ?>\'?')" 
                               class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-sm-xl text-sm font-bold shadow-md shadow-blue-100 transition-all active:scale-95">
                                <i class="fas fa-plus mr-1"></i> Pinjam
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10">
                    <p class="text-gray-500">Buku tidak ditemukan.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- BUKU SEDANG DIPINJAM -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-12 no-print">
            <div class="bg-blue-50 px-6 py-4 border-b border-blue-100 flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                    <h3 class="text-xl font-bold text-blue-800"><i class="fas fa-clock mr-2"></i>Buku Sedang Dipinjam</h3>
                    <span class="bg-blue-200 text-blue-800 text-xs font-bold px-2 py-0.5 rounded mt-1 inline-block">
                        <?= ($myLoans) ? mysqli_num_rows($myLoans) : 0 ?> Buku Aktif
                    </span>
                </div>
                <form action="" method="GET" class="w-full md:w-64">
                    <input type="hidden" name="search" value="<?= $search ?>">
                    <input type="hidden" name="search_history" value="<?= $search_history ?>">
                    <div class="relative">
                        <input type="text" name="search_active" value="<?= $search_active ?>" placeholder="Cari judul buku..." 
                               class="w-full pl-8 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 outline-none">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    </div>
                </form>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-400 text-xs uppercase bg-gray-50 border-b">
                            <th class="px-6 py-4 font-semibold">Judul Buku</th>
                            <th class="px-6 py-4 font-semibold">Tanggal Pinjam</th>
                            <th class="px-6 py-4 font-semibold">Batas Kembali</th>
                            <th class="px-6 py-4 font-semibold text-center">Status</th>
                            <th class="px-6 py-4 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php if($myLoans && mysqli_num_rows($myLoans) > 0): ?>
                            <?php while($l = mysqli_fetch_assoc($myLoans)): ?>
                            <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                <td class="px-6 py-4 font-bold text-gray-700"><?= $l['title'] ?></td>
                                <td class="px-6 py-4 text-gray-500"><?= date('d/m/Y', strtotime($l['loan_date'])) ?></td>
                                <td class="px-6 py-4 text-gray-500"><?= date('d/m/Y', strtotime($l['return_date'])) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold border border-yellow-200">
                                        Dipinjam
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="?kembali=<?= $l['id'] ?>&search=<?= $search ?>&search_active=<?= $search_active ?>&search_history=<?= $search_history ?>" 
                                       onclick="return confirm('Kembalikan buku ini sekarang?')"
                                       class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow-sm hover:shadow">
                                        <i class="fas fa-undo mr-1"></i> Kembalikan
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                    Data tidak ditemukan atau kosong.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIWAYAT PEMINJAMAN -->
        <div id="printable-history" class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-12">
            <div class="bg-gray-100 px-6 py-4 border-b border-gray-200 flex flex-col md:flex-row justify-between md:items-center gap-4 no-print">
                <div>
                    <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-history mr-2"></i>Riwayat Peminjaman</h3>
                </div>
                <div class="flex gap-2">
                    <form action="" method="GET" class="w-full md:w-64">
                        <input type="hidden" name="search" value="<?= $search ?>">
                        <input type="hidden" name="search_active" value="<?= $search_active ?>">
                        <div class="relative">
                            <input type="text" name="search_history" value="<?= $search_history ?>" placeholder="Cari riwayat..." 
                                   class="w-full pl-8 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 outline-none">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                        </div>
                    </form>
                    <button onclick="window.print()" class="bg-gray-800 hover:bg-black text-white px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 whitespace-nowrap">
                        <i class="fas fa-print"></i> Cetak PDF
                    </button>
                </div>
            </div>
            
            <h2 class="print-only-title">Laporan Riwayat Peminjaman</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-400 text-xs uppercase bg-gray-50 border-b">
                            <th class="px-6 py-4 font-semibold">Judul Buku</th>
                            <th class="px-6 py-4 font-semibold">Tanggal Pinjam</th>
                            <th class="px-6 py-4 font-semibold">Tanggal Kembali</th>
                            <th class="px-6 py-4 font-semibold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php if($myHistory && mysqli_num_rows($myHistory) > 0): ?>
                            <?php while($h = mysqli_fetch_assoc($myHistory)): ?>
                            <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                                <td class="px-6 py-4 font-bold text-gray-700"><?= $h['title'] ?></td>
                                <td class="px-6 py-4 text-gray-500"><?= date('d/m/Y', strtotime($h['loan_date'])) ?></td>
                                <td class="px-6 py-4 text-gray-500"><?= date('d/m/Y', strtotime($h['return_date'])) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="bg-gray-200 text-gray-600 px-3 py-1 rounded-full text-xs font-bold border border-gray-300">
                                        Selesai
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                                    Data tidak ditemukan atau kosong.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-4 text-center text-xs text-gray-400 print:hidden mt-2">
                Dicetak pada: <?= date('d-m-Y H:i') ?>
            </div>
        </div>

    </div>

    <footer class="text-center py-8 text-gray-400 text-sm border-t border-gray-200 no-print">
        &copy; 2026 Perpustakaan Digital smechatwolasma &bull; Sistem Sempurna
    </footer> 
</body>
</html>