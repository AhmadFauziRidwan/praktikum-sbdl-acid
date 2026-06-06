<?php
date_default_timezone_set('Asia/Jakarta');
include 'db.php';

// Ambil data untuk Dashboard Stats
$total_types = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_stock = $conn->query("SELECT SUM(stock) FROM products")->fetchColumn();
$total_orders = $conn->query("SELECT COUNT(*) FROM pokemon_orders")->fetchColumn();

// Ambil data produk asli dari SQL
$products = $conn->query("SELECT sku, title, price, stock, category FROM products LIMIT 6")->fetchAll();
// Ambil riwayat order
$orders = $conn->query("SELECT * FROM pokemon_orders ORDER BY id DESC LIMIT 5")->fetchAll();
// Ambil log transaksi
$logs = $conn->query("SELECT * FROM db_transaction_logs ORDER BY id DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ACID & Transaction Simulator Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <header>
        <h1>DATABASE TRANSACTION LAB</h1>
        <p>Implementasi Nyata Prinsip ACID (Atomicity, Consistency, Isolation, Durability)</p>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Varian</h3>
            <p class="stat-number"><?= $total_types ?> Pokémon</p>
        </div>
        <div class="stat-card">
            <h3>Total Stok Global</h3>
            <p class="stat-number"><?= number_format($total_stock) ?> Pcs</p>
        </div>
        <div class="stat-card">
            <h3>Pesanan Sukses (Durability)</h3>
            <p class="stat-number" style="color: #2f855a;"><?= $total_orders ?> Transaksi</p>
        </div>
    </div>

    <div class="main-layout">
        <div class="content-left">
            <h2>Data Tabel `products`</h2>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Pokémon</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Aksi Simulasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p) { ?>
                    <tr>
                        <td><strong>#<?= $p['sku'] ?></strong></td>
                        <td><?= htmlspecialchars($p['title']) ?></td>
                        <td><small><?= htmlspecialchars($p['category']) ?></small></td>
                        <td>
                            <?php if($p['stock'] > 0) { ?>
                                <span class="badge bg-success">Tersedia (<?= $p['stock'] ?>)</span>
                            <?php } else { ?>
                                <span class="badge bg-danger">Habis (0)</span>
                            <?php } ?>
                        </td>
                        <td>
                            <form action="buy.php" method="POST" style="display:inline;">
                                <input type="hidden" name="sku" value="<?= $p['sku'] ?>">
                                <input type="hidden" name="mode" value="standard">
                                <button type="submit" class="btn btn-sm btn-primary">Test ACID Buy</button>
                            </form>
                            <form action="buy.php" method="POST" style="display:inline;">
                                <input type="hidden" name="sku" value="<?= $p['sku'] ?>">
                                <input type="hidden" name="mode" value="savepoint_demo">
                                <button type="submit" class="btn btn-sm btn-warning">Test Savepoint</button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>

            <h2>Isian Tabel `pokemon_orders`</h2>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>ID Order</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Waktu Sinkron</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($orders)) { ?>
                        <tr><td colspan="4" style="color: #a0aec0;">Belum ada data yang berhasil di-COMMIT ke tabel order.</td></tr>
                    <?php } ?>
                    <?php foreach($orders as $o) { ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= $o['sku'] ?></td>
                        <td><?= $o['quantity'] ?></td>
                        <td><?= $o['order_date'] ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="content-right">
            <h2>Engine Transaction Console</h2>
            <div class="terminal-box">
                <div class="terminal-header">
                    <span class="dot red"></span><span class="dot yellow"></span><span class="dot green"></span>
                    <span class="terminal-title">logs@transaction-engine:~</span>
                </div>
                <div class="terminal-body">
                    <?php if(empty($logs)) { ?>
                        <p class="log-line text-muted">> Belum ada aktivitas transaksi. Silakan klik tombol aksi di kiri.</p>
                    <?php } else {
                        $logs_reversed = array_reverse($logs);
                        $in_block = false;
                        foreach($logs_reversed as $l) {
                            if ($l['status'] === 'BEGIN') {
                                if ($in_block) { echo "</div>"; }
                                echo "<div class='transaction-block' style='border-left: 4px solid #3182ce; background: rgba(49, 130, 206, 0.04); padding: 10px; margin-top: 15px; margin-bottom: 15px; border-radius: 0 6px 6px 0;'>";
                                $in_block = true;
                            }
                    ?>
                        <p class="log-line" style="margin: 6px 0; font-family: monospace; font-size: 0.85rem; color: #e2e8f0;">
                            <span class="log-time">[<?= substr($l['timestamp'], 11, 8) ?>]</span>
                            <span class="log-cmd">
                                <?php
                                if($l['status'] === 'BEGIN') {
                                    echo "<span style='color: #63b3ed;'><strong>" . htmlspecialchars($l['command']) . "</strong></span><br><small style='color: #a0aec0;'><i>> Prinsip Isolation dimulai. Membuat ruang transaksi terisolasi.</i></small>";
                                } elseif ($l['status'] === 'COMMIT') {
                                    echo "<span style='color: #68d391;'><strong>" . htmlspecialchars($l['command']) . "</strong></span><br><small style='color: #a0aec0;'><i>> Prinsip Durability & Consistency terpenuhi. Data dikunci permanen.</i></small>";
                                } elseif ($l['status'] === 'ROLLBACK') {
                                    echo "<span style='color: #fc8181;'><strong>" . htmlspecialchars($l['command']) . "</strong></span><br><small style='color: #a0aec0;'><i>> Prinsip Atomicity bekerja! Semua operasi di dalam blok ini dibatalkan total.</i></small>";
                                } elseif ($l['status'] === 'PARTIAL_ROLLBACK') {
                                    echo "<span style='color: #f6ad55;'><strong>" . htmlspecialchars($l['command']) . "</strong></span><br><small style='color: #a0aec0;'><i>> Kembali ke checkpoint tertentu. Operasi sebelum checkpoint tetap dipertahankan.</i></small>";
                                } else {
                                    echo htmlspecialchars($l['command']);
                                }
                                ?>
                            </span>
                            <span class="log-status <?= strtolower($l['status']) ?>" style="float: right; font-weight: bold; font-size: 0.75rem;">[<?= $l['status'] ?>]</span>
                        </p>
                    <?php
                            if ($l['status'] === 'COMMIT' || $l['status'] === 'ROLLBACK') {
                                echo "</div>";
                                $in_block = false;
                            }
                        }
                        if ($in_block) { echo "</div>"; }
                    } ?>
                </div>
                <p class="terminal-hint">*Konsol ini membalik urutan pembacaan data (kronologis maju) dan mengelompokkan baris query menggunakan kotak penanda agar siklus hidup transaksi terlihat utuh.</p>
            </div>
        </div>
    </div>
</div>
<script>
    var terminalBody = document.querySelector('.terminal-body');
    if(terminalBody) { terminalBody.scrollTop = terminalBody.scrollHeight; }
</script>
</body>
</html>