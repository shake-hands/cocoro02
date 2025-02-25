<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

session_start();
require "component.php"; // 包含商品資訊的檔案

// 頁面模式：預設為購物頁
$page = isset($_GET['page']) ? $_GET['page'] : 'shopping';

// 初始化購物車
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = &$_SESSION['cart'];

// 計算合計金額
function calculate_total($cart, $product, $shipping = 0)
{
    $total = 0;
    foreach ($cart as $item) {
        $product_key = array_search($item['product_name'], array_column($product, 'product_name'));
        if ($product_key !== false) {
            $price = $product[$product_key]['price'];
            $total += $price * $item['num'];
        }
    }
    return $total + $shipping;
}

// 發送電子郵件
function send_email($to, $subject, $message, $checkout_email)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'www937.sakura.ne.jp';
        $mail->SMTPAuth = true;
        $mail->Username = 'himetooru621025@tousatsukikoubou.sakura.ne.jp';
        $mail->Password = 'tora4f2d';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('himetooru621025@tousatsukikoubou.sakura.ne.jp', 'Web Shop');
        $mail->addAddress($to);
        $mail->addReplyTo($checkout_email);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("郵件發送失敗: {$mail->ErrorInfo}");
        return false;
    }
}

// 表單提交處理
$order_summary = '';

// 處理購物頁表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'shopping') {
    if (isset($_POST['product_name'], $_POST['num'])) {
        $product_name = htmlspecialchars($_POST['product_name']);
        $num = intval($_POST['num']);

        // 檢查商品是否已在購物車中
        $existing_key = array_search($product_name, array_column($cart, 'product_name'));
        if ($existing_key !== false) {
            $cart[$existing_key]['num'] += $num;
        } else {
            $cart[] = ['product_name' => $product_name, 'num' => $num];
        }
    }
}

// 處理結帳頁表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'checkout') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $shipping_method = filter_input(INPUT_POST, 'shipping', FILTER_SANITIZE_SPECIAL_CHARS);

    $shipping_cost = 800;
// 刪除商品
if (isset($_POST['delete_product'])) {
    $delete_product = htmlspecialchars($_POST['delete_product']);
    foreach ($cart as $key => $item) {
        if ($item['product_name'] === $delete_product) {
            unset($cart[$key]);
            $cart = array_values($cart); // 重置索引
            break;
        }
    }
}
    if (!$name || !$address || !$email || !$shipping_method) {
        $order_summary = "<p class='text-danger'>請完整填寫所有資訊。</p>";
    } else {
        // 建立購買清單摘要
        $order_summary = "<h2>訂單摘要</h2>";
        $order_summary .= "<p>名前: {$name}</p>";
        $order_summary .= "<p>住所: {$address}</p>";
        $order_summary .= "<p>E-mail: {$email}</p>";
        $order_summary .= "<h3>購買清單：</h3><ul>";

        foreach ($cart as $item) {
            $product_key = array_search($item['product_name'], array_column($product, 'product_name'));
            $price = $product[$product_key]['price'];
            $order_summary .= "<li>{$item['product_name']} x {$item['num']} = " . number_format($price * $item['num']) . "円</li>";
        }

        $order_summary .= "</ul>";
        $order_summary .= "<p>送料: " . number_format($shipping_cost) . "円</p>";
        $order_summary .= "<p>合計金額: " . number_format(calculate_total($cart, $product, $shipping_cost)) . "円</p>";

        // 發送郵件
        $to = "himetooru621025@tousatsukikoubou.sakura.ne.jp";
        $subject = "注文確認";
        if (send_email($to, $subject, strip_tags($order_summary), $email)) {
            unset($_SESSION['cart']);
            $order_summary .= "<p class='text-success'>訂單已成功送出！</p>";
        } else {
            $order_summary .= "<p class='text-danger'>無法發送訂單確認信，請稍後重試。</p>";
        }
    }
}
?>

<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>MIRA Shopping Shop</title>
    <style>
    .product-image {
        width: 50px !important; /* 縮小圖片 */
        height: auto !important; /* 保持比例 */
        display: block; /* 避免受到 Bootstrap 影響 */
        margin: auto; /* 置中顯示 */
    }
</style>

</head>
<body>

<div class="container mt-3">
  <?php if ($page === 'shopping'): ?>
        <h1>ショッピングページ</h1>
        <div class="row">
            <?php foreach ($product as $item): ?>
                <div class="col-md-5 mb-5">
                    <div class="card">
                    <img src="<?= htmlspecialchars($item['img']) ?>" class="product-image img-thumbnail" alt="<?= htmlspecialchars($item['product_name']) ?>">
                    
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($item['product_name']) ?></h5>
                            <p class="card-text">価格：<?= number_format($item['price']) ?>円</p>
                            <form method="post">
                                <input type="hidden" name="product_name" value="<?= htmlspecialchars($item['product_name']) ?>">
                                <select name="num" class="form-select mb-2">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                                <button type="submit" class="btn btn-primary">ショッピングカードに入れる</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <h2>ショッピングカード</h2>
        <ul class="list-group mb-3">
            <?php foreach ($cart as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($item['product_name']) ?> x <?= $item['num'] ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete_product" value="<?= htmlspecialchars($item['product_name']) ?>">
                        <button type="submit" class="btn btn-danger btn-sm">削除</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="?page=checkout" class="btn btn-success">ご会計へ</a>
    <?php elseif ($page === 'checkout'): ?>
        <h1>購入ページ</h1>
        <?php if ($order_summary): ?>
            <?= $order_summary; ?>
        <?php else: ?>
            <form method="post">
                <div class="mb-3">
                    <label for="name" class="form-label">名前</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">住所</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="shipping" class="form-label">運送方式</label>
                    <input type="text" class="form-control" id="shipping" name="shipping" value="送料:800円" readonly>
                </div>
                <button type="submit" class="btn btn-primary">送信</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
