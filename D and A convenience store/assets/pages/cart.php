<?php
session_start();

$host = "localhost";
$username = "root";
$password = "";
$database = "da_convenience_store";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// CREATE: Add product to cart
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $product_id = filter_input(
            INPUT_POST,
            "product_id",
            FILTER_VALIDATE_INT
        );

        $quantity = filter_input(
            INPUT_POST,
            "quantity",
            FILTER_VALIDATE_INT
        );

        if ($product_id && $quantity && $quantity > 0) {
            $stmt = $conn->prepare(
                "SELECT id FROM products WHERE id = ?"
            );
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($product) {
                $stmt = $conn->prepare(
                    "INSERT INTO cart (product_id, quantity)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE
                     quantity = quantity + VALUES(quantity)"
                );

                $stmt->bind_param("ii", $product_id, $quantity);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // UPDATE: Change quantity
    if ($action === "update") {
        $cart_id = filter_input(
            INPUT_POST,
            "cart_id",
            FILTER_VALIDATE_INT
        );

        $quantity = filter_input(
            INPUT_POST,
            "quantity",
            FILTER_VALIDATE_INT
        );

        if ($cart_id && $quantity && $quantity > 0) {
            $stmt = $conn->prepare(
                "UPDATE cart SET quantity = ? WHERE id = ?"
            );
            $stmt->bind_param("ii", $quantity, $cart_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // DELETE: Remove item or clear cart
    if ($action === "delete") {
        $cart_id = filter_input(
            INPUT_POST,
            "cart_id",
            FILTER_VALIDATE_INT
        );

        if ($cart_id) {
            $stmt = $conn->prepare(
                "DELETE FROM cart WHERE id = ?"
            );
            $stmt->bind_param("i", $cart_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === "clear") {
        $conn->query("DELETE FROM cart");
    }

    header("Location: cart.php");
    exit;
}

// READ: Retrieve all cart products
$sql = "SELECT
            cart.id AS cart_id,
            products.id AS product_id,
            products.name,
            products.price,
            products.image,
            cart.quantity,
            (products.price * cart.quantity) AS subtotal
        FROM cart
        INNER JOIN products
        ON cart.product_id = products.id
        ORDER BY cart.id DESC";

$result = $conn->query($sql);

$items = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
    $total += (float) $row["subtotal"];
}

$itemCount = count($items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Cart | D & A Convenience Store</title>
    <link rel="stylesheet" href="../styles/cart.css" />
    <link rel="stylesheet" href="../product.css" />
</head>

<body>

<header>
    <div class="header-main">

        <section class="header-logo">
            <img src="../images/logo1.jpg" alt="D & A Logo">

            <div class="store-name">
                <h2>
                    <span class="orange"><i>D & A</i></span>
                    <span class="blue"><i>CONVENIENCE STORE</i></span>
                </h2>

                <div class="korean-line">
                    <hr class="line">
                    <span class="korean"><i>(편의점)</i></span>
                    <hr class="line">
                </div>
            </div>
        </section>

        <nav class="header-nav">
            <ul>
                <li><a href="../../index.html">HOME</a></li>
                <li><a href="product.html">PRODUCTS</a></li>
                <li><a href="about.html">ABOUT D & A</a></li>
                <li><a href="cart.php">MY CART (<?= $itemCount ?>)</a></li>
            </ul>
        </nav>

    </div>
</header>

<main class="cart-page">

    <section class="cart-heading">
        <p class="cart-eyebrow">D & A CONVENIENCE STORE</p>
        <h1>My Shopping Cart</h1>
        <p>Everything you need, all in one place.</p>
    </section>

    <section class="cart-container">

        <div class="cart-products">

            <div class="cart-title">
                <h2>Your Items</h2>
                <span><?= $itemCount ?> product(s)</span>
            </div>

            <?php if (empty($items)): ?>

                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h2>Your cart is empty</h2>
                    <p>Discover your everyday essentials.</p>
                    <a href="product.html" class="shop-button">
                        Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>

                    <article class="cart-item">

                        <div class="cart-item-image">
                            <?php if (!empty($item["image"])): ?>
                                <img
                                    src="../images/<?= htmlspecialchars(
                                        $item["image"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $item["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                >
                            <?php else: ?>
                                <span>🛍️</span>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-details">
                            <h3>
                                <?= htmlspecialchars($item["name"]) ?>
                            </h3>
                            <p class="item-price">
                                ₱<?= number_format(
                                    (float) $item["price"],
                                    2
                                ) ?>
                            </p>
                            <form method="POST" action="cart.php"
                                  class="quantity-form">
                                <input type="hidden"
                                       name="action"
                                       value="update">
                                <input type="hidden"
                                       name="cart_id"
                                       value="<?= (int) $item["cart_id"] ?>">
                                <label for="qty-<?= (int) $item["cart_id"] ?>">
                                    Quantity:
                                </label>
                                <input
                                    id="qty-<?= (int) $item["cart_id"] ?>"
                                    type="number"
                                    name="quantity"
                                    min="1"
                                    max="999"
                                    value="<?= (int) $item["quantity"] ?>"
                                    required
                                >
                                <button type="submit" class="update-button">
                                    Update
                                </button>
                            </form>
                            <form method="POST" action="cart.php"
                                  class="delete-form"
                                  onsubmit="return confirm('Remove this product from your cart?');">
                                <input type="hidden"
                                       name="action"
                                       value="delete">
                                <input type="hidden"
                                       name="cart_id"
                                       value="<?= (int) $item["cart_id"] ?>">
                                <button type="submit" class="remove-button">
                                    Remove item
                                </button>
                            </form>
                        </div>
                        <div class="cart-item-subtotal">
                            <span>Subtotal</span>
                            <strong>
                                ₱<?= number_format(
                                    (float) $item["subtotal"],
                                    2
                                ) ?>
                            </strong>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <aside class="cart-summary">
            <h2>Order Summary</h2>
            <div class="summary-row">
                <span>Products</span>
                <span><?= $itemCount ?></span>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <span>₱<?= number_format($total, 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Delivery</span>
                <span>₱0.00</span>
            </div>
            <div class="summary-total">
                <span>Total</span>
                <strong>₱<?= number_format($total, 2) ?></strong>
            </div>
            <a href="product.html" class="continue-button">
                Continue Shopping
            </a>
            <?php if (!empty($items)): ?>
                <form method="POST"
                      action="cart.php"
                      onsubmit="return confirm('Clear all items from the cart?');">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="clear-button">
                        Clear Entire Cart
                    </button>
                </form>
            <?php endif; ?>
            <p class="summary-note">
                Prices are displayed in Philippine pesos (PHP).
            </p>
        </aside>
    </section>
</main>

<footer class="cart-footer">
    <p>&copy; <?= date("Y") ?> D & A Convenience Store.</p>
    <p>Your convenience is our priority.</p>
</footer>

</body>
</html>

<?php
$conn->close();
?>