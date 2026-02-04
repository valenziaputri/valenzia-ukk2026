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

if (isset($_GET['pinjam'])) {
    $book_id = (int)$_GET['pinjam'];
    $loan_date = date('Y-m-d');
    $return_date = date('Y-m-d', strtotime('+7 days'));
    $check_stock = mysqli_query($conn, "SELECT stock FROM books WHERE id = '$book_id'");
    
    if ($check_stock && mysqli_num_rows($check_stock) > 0) {
        $s = mysqli_fetch_assoc($check_stock);
        
        if ($s['stock'] > 0) {
            $insert = mysqli_query($conn, "INSERT INTO loans (user_id, book_id, loan_date, return_date, status) VALUES ('$user_id', '$book_id', '$loan_date', '$return_date', 'dipinjam')");
            $update = mysqli_query($conn, "UPDATE books SET stock = stock - 1 WHERE id = '$book_id'");
            
            if ($insert && $update) {
                header("Location: index.php?msg=Buku berhasil dipinjam!");
            } else {
                header("Location: index.php?msg=Gagal memproses peminjaman.");
            }
        } else {
            header("Location: index.php?msg=Maaf, stok habis!");
        }
    } else {
        header("Location: index.php?msg=Buku tidak ditemukan.");
    }
    exit();
}

if (isset($_GET['kembali'])) {
    $loan_id = $_GET['kembali'];
    $qLoan = mysqli_query($conn, "SELECT * FROM loans WHERE id = '$loan_id' AND user_id = '$user_id' AND status = 'dipinjam'");
    
    if ($qLoan && mysqli_num_rows($qLoan) > 0) {
        $dLoan = mysqli_fetch_assoc($qLoan);
        $book_id = $dLoan['book_id'];
        $upLoan = mysqli_query($conn, "UPDATE loans SET status = 'kembali' WHERE id = '$loan_id'");
        $upBook = mysqli_query($conn, "UPDATE books SET stock = stock + 1 WHERE id = '$book_id'");
        
        if ($upLoan && $upBook) {
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
 $search = "";
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    // Tambahkan filter pencarian ke kolom kondisi juga
    $query = "SELECT * FROM books WHERE title LIKE '%$search%' OR author LIKE '%$search%' OR kondisi LIKE '%$search%' ORDER BY id DESC";
} else {
    $query = "SELECT * FROM books ORDER BY id DESC";
}
 $books = mysqli_query($conn, $query);

// --- MODIFIKASI 1: AMBIL INPUT PENCARIAN UNTUK PEMINJAMAN & RIWAYAT ---
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
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded shadow-sm no-print">
                <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-800">Katalog Perpustakaan</h2>
                <p class="text-gray-500">Halo <span class="text-blue-600 font-bold"><?= $_SESSION['username'] ?? 'Siswa' ?></span>, temukan bukumu!</p>
            </div>
            
            <form action="" method="GET" class="relative w-full md:w-80 no-print">
                <input type="text" name="search" value="<?= $search ?>" placeholder="Cari judul, penulis, kondisi..." 
                       class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none shadow-sm">
                <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                <!-- Input hidden agar parameter pencarian bawah tidak hilang saat cari katalog -->
                <input type="hidden" name="search_active" value="<?= $search_active ?>">
                <input type="hidden" name="search_history" value="<?= $search_history ?>">
            </form>
        </div>

        <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2"><i class="fas fa-layer-group mr-2"></i>Daftar Buku</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12 no-print">
            <?php if($books && mysqli_num_rows($books) > 0): ?>
                <?php while($b = mysqli_fetch_assoc($books)): 
                    // Fallback data kondisi
                    $kondisi = isset($b['kondisi']) ? $b['kondisi'] : 'Baik';
                    $persentase = isset($b['persentase_kondisi']) ? (int)$b['persentase_kondisi'] : 90;

                    // Tentukan warna badge & progress bar
                    if($persentase >= 80) {
                        $color_class = 'text-green-700 bg-green-100 border-green-200';
                        $bar_color = 'bg-green-500';
                    } elseif($persentase >= 50) {
                        $color_class = 'text-yellow-700 bg-yellow-100 border-yellow-200';
                        $bar_color = 'bg-yellow-400';
                    } else {
                        $color_class = 'text-red-700 bg-red-100 border-red-200';
                        $bar_color = 'bg-red-500';
                    }
                ?>
                <div class="bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl transition-all border border-gray-100 flex flex-col h-full justify-between group">
                    <div>
                        <div class="mb-3 flex justify-between items-start">
                            <span class="<?= $b['stock'] > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wide">
                                <?= $b['stock'] > 0 ? 'Stok: ' . $b['stock'] : 'Habis' ?>
                            </span>
                            <div class="w-8 h-8 bg-blue-50 rounded-full flex items-center justify-center text-blue-400">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                        
                        <h3 class="font-bold text-lg text-gray-800 mb-2 leading-snug line-clamp-2">
                            <?= $b['title'] ?>
                        </h3>
                        
                        <p class="text-sm text-gray-500 flex items-center gap-2 mb-1">
                            <i class="fas fa-pen-nib text-xs"></i> <?= $b['author'] ?>
                        </p>

                        <!-- TAMBAHAN: DISPLAY KONDISI BUKU -->
                        <div class="mt-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">Kondisi Fisik</span>
                                <span class="text-[10px] font-bold <?= $persentase >= 50 ? 'text-green-600' : 'text-red-600' ?>"><?= $kondisi ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="<?= $bar_color ?> h-1.5 rounded-full transition-all duration-500" style="width: <?= $persentase ?>%"></div>
                            </div>
                            <div class="text-right mt-1">
                                <span class="text-[10px] font-bold text-gray-400"><?= $persentase ?>%</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <?php if($b['stock'] > 0): ?>
                            <a href="?pinjam=<?= $b['id'] ?>&search=<?= $search ?>&search_active=<?= $search_active ?>&search_history=<?= $search_history ?>" 
                               onclick="return confirm('Apakah Anda yakin ingin meminjam buku \'<?= addslashes($b['title']) ?>\'?')" 
                               class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl text-sm font-bold shadow-md shadow-blue-100 transition-all active:scale-95">
                                <i class="fas fa-plus mr-1"></i> Pinjam
                            </a>
                        <?php else: ?>
                            <button disabled class="w-full bg-gray-100 text-gray-400 py-2.5 rounded-xl text-sm font-bold cursor-not-allowed border border-gray-200">
                                Stok Kosong
                            </button>
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
                <!-- MODIFIKASI 2: FORM PENCARIAN PEMINJAMAN AKTIF -->
                <form action="" method="GET" class="w-full md:w-64">
                    <input type="hidden" name="search" value="<?= $search ?>"> <!-- Menyimpan state katalog -->
                    <input type="hidden" name="search_history" value="<?= $search_history ?>"> <!-- Menyimpan state riwayat -->
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

        <!-- RIWAYAT PEMINJAMAN (BARU + FITUR PRINT + PENCARIAN) -->
        <div id="printable-history" class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden mb-12">
            <div class="bg-gray-100 px-6 py-4 border-b border-gray-200 flex flex-col md:flex-row justify-between md:items-center gap-4 no-print">
                <div>
                    <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-history mr-2"></i>Riwayat Peminjaman</h3>
                </div>
                <div class="flex gap-2">
                    <!-- MODIFIKASI 3: FORM PENCARIAN RIWAYAT -->
                    <form action="" method="GET" class="w-full md:w-64">
                        <input type="hidden" name="search" value="<?= $search ?>"> <!-- Menyimpan state katalog -->
                        <input type="hidden" name="search_active" value="<?= $search_active ?>"> <!-- Menyimpan state aktif -->
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
            
            <!-- Judul yang hanya muncul saat Print -->
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
            <!-- Footer Kecil untuk Print (Tanggal Cetak) -->
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