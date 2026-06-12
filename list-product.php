<?php 
require_once 'config.php'; 
session_start();

// Only sellers can access
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id=?");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();
if ($role !== 'seller') { 
    echo "Access denied."; 
    exit; 
}

$error = $success = '';

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

function uploadProductImage($fileKey, &$error) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== 0) {
        return '';
    }

    $target_dir = "uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    $image_name = time() . '_' . basename($_FILES[$fileKey]['name']);
    $target_file = $target_dir . $image_name;

    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target_file)) {
        return $image_name;
    }

    $error = "Image upload failed.";
    return '';
}

function buildProductStock($pdo, $category, $quantityInput, $sizesInput, &$error) {
    $quantity = max(1, (int)$quantityInput);
    $clothing_size_stock = null;

    $stmt = $pdo->prepare("SELECT name FROM categories WHERE cat_id=?");
    $stmt->execute([$category]);
    $categoryName = $stmt->fetchColumn();
    $isClothing = isClothingCategoryName($categoryName);

    if ($isClothing) {
        $sizeStock = [];
        foreach (clothingSizes() as $size) {
            $sizeStock[$size] = max(0, (int)($sizesInput[$size] ?? 0));
        }

        $quantity = totalClothingStock($sizeStock);
        if ($quantity < 1) {
            $error = "Please enter quantity for at least one clothing size.";
        } else {
            $clothing_size_stock = encodeClothingSizeStock($sizeStock);
        }
    }

    return [$quantity, $clothing_size_stock];
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_product') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $category = $_POST['category'];
    $location = trim($_POST['location']);
    [$quantity, $clothing_size_stock] = buildProductStock($pdo, $category, $_POST['quantity'] ?? 1, $_POST['sizes'] ?? [], $error);
    $image_url = uploadProductImage('image', $error);
    
    try {
        if ($error) {
            throw new Exception($error);
        }

        $stmt = $pdo->prepare("INSERT INTO products 
            (title, description, price, quantity, status, user_id, cat_id, image_url, location, clothing_size_stock, rejection_reason, created_at) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, NULL, NOW())");
        $stmt->execute([$title, $description, $price, $quantity, $_SESSION['user_id'], $category, $image_url, $location, $clothing_size_stock]);
        $success = "Product submitted successfully. It will appear in the marketplace after admin approval.";
    } catch (Exception $e) {
        $error = "Failed to add product: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_product') {
    $prod_id = filter_var($_POST['prod_id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if (!$prod_id) {
            throw new Exception("Invalid product selected.");
        }

        $stmt = $pdo->prepare("SELECT * FROM products WHERE prod_id=? AND user_id=?");
        $stmt->execute([$prod_id, $_SESSION['user_id']]);
        $existingProduct = $stmt->fetch();
        if (!$existingProduct) {
            throw new Exception("Product not found.");
        }

        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = $_POST['price'];
        $category = $_POST['category'];
        $location = trim($_POST['location']);
        [$quantity, $clothing_size_stock] = buildProductStock($pdo, $category, $_POST['quantity'] ?? 1, $_POST['sizes'] ?? [], $error);
        $newImage = uploadProductImage('image', $error);
        $image_url = $newImage ?: ($existingProduct['image_url'] ?? '');

        if ($error) {
            throw new Exception($error);
        }

        $stmt = $pdo->prepare("UPDATE products
            SET title=?, description=?, price=?, quantity=?, status='pending', cat_id=?, image_url=?, location=?, clothing_size_stock=?, rejection_reason=NULL
            WHERE prod_id=? AND user_id=?");
        $stmt->execute([$title, $description, $price, $quantity, $category, $image_url, $location, $clothing_size_stock, $prod_id, $_SESSION['user_id']]);
        $success = "Product updated and sent back to admin for approval.";
    } catch (Exception $e) {
        $error = "Failed to update product: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    $prod_id = filter_var($_POST['prod_id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if (!$prod_id) {
            throw new Exception("Invalid product selected.");
        }

        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE prod_id=? AND user_id=?");
        $stmt->execute([$prod_id, $_SESSION['user_id']]);
        $productToDelete = $stmt->fetch();
        if (!$productToDelete) {
            throw new Exception("Product not found.");
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE prod_id=?");
        $stmt->execute([$prod_id]);
        $hasOrders = (int)$stmt->fetchColumn() > 0;

        if ($hasOrders) {
            $stmt = $pdo->prepare("UPDATE products SET status='removed' WHERE prod_id=? AND user_id=?");
            $stmt->execute([$prod_id, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM products WHERE prod_id=? AND user_id=?");
            $stmt->execute([$prod_id, $_SESSION['user_id']]);

            if (!empty($productToDelete['image_url'])) {
                $imagePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($productToDelete['image_url']);
                if (is_file($imagePath)) {
                    unlink($imagePath);
                }
            }
        }

        $success = "Product removed from your listings and the marketplace.";
    } catch (Exception $e) {
        $error = "Failed to remove product: " . $e->getMessage();
    }
}

// Fetch seller's products
$stmt = $pdo->prepare("SELECT * FROM products WHERE user_id=? AND status <> 'removed' ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$myProducts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Listings - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-shop me-1"></i>InformalTrade Hub</a>
            <a class="btn btn-outline-light ms-auto" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
    </nav>
    <div class="container page-shell">
        <h2><i class="bi bi-box-seam me-2"></i>List New Product</h2>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <!-- Product Listing Form -->
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_product">
            <div class="mb-3"><label>Product Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="mb-3"><label>Price (ZAR)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="mb-3" id="standardQuantityGroup"><label>Quantity</label><input type="number" min="1" name="quantity" class="form-control" value="1" required></div>
            <div class="mb-3"><label>Description</label><textarea name="description" class="form-control" rows="4" required></textarea></div>
            <div class="mb-3"><label>Category</label>
                <select name="category" id="categorySelect" class="form-select">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['cat_id'] ?>" data-is-clothing="<?= isClothingCategoryName($cat['name']) ? '1' : '0' ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3 d-none" id="clothingSizesGroup">
                <label>Clothing size quantities</label>
                <div class="row g-2">
                    <?php foreach (clothingSizes() as $size): ?>
                        <div class="col-6 col-md-2">
                            <label class="form-label text-uppercase small"><?= htmlspecialchars($size) ?></label>
                            <input type="number" min="0" name="sizes[<?= htmlspecialchars($size) ?>]" class="form-control clothing-size-input" value="0">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3"><label>Location</label><input type="text" name="location" class="form-control" required></div>
            <div class="mb-3"><label>Product Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Submit Product</button>
        </form>

        <!-- Seller's Listings -->
        <h3 class="mt-5"><i class="bi bi-list-ul me-2"></i>My Listings</h3>
        <?php if ($myProducts): ?>
            <table class="table table-bordered">
                <thead><tr><th>ID</th><th>Title</th><th>Price</th><th>Quantity</th><th>Status</th><th>Location</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($myProducts as $p): ?>
                        <?php $editSizeStock = decodeClothingSizeStock($p['clothing_size_stock'] ?? ''); ?>
                        <tr>
                            <td><?= $p['prod_id'] ?></td>
                            <td><?= htmlspecialchars($p['title']) ?></td>
                            <td>R<?= $p['price'] ?></td>
                            <td><?= (int)($p['quantity'] ?? 0) ?></td>
                            <td>
                                <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
                                <?php if (($p['status'] ?? '') === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Awaiting approval</span>
                                <?php elseif (($p['status'] ?? '') === 'sold_out'): ?>
                                    <span class="badge bg-secondary">Sold out</span>
                                <?php elseif (($p['status'] ?? '') === 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                    <?php if (!empty($p['rejection_reason'])): ?>
                                        <div class="small text-danger mt-1"><strong>Reason:</strong> <?= htmlspecialchars($p['rejection_reason']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['location']) ?></td>
                            <td><?= $p['created_at'] ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm edit-toggle" data-edit-target="edit-product-<?= $p['prod_id'] ?>">
                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this product permanently?');">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="prod_id" value="<?= $p['prod_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-product-<?= $p['prod_id'] ?>" class="d-none edit-product-row">
                            <td colspan="8">
                                <form method="POST" enctype="multipart/form-data" class="border rounded p-3 bg-light edit-product-form">
                                    <input type="hidden" name="action" value="edit_product">
                                    <input type="hidden" name="prod_id" value="<?= $p['prod_id'] ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label>Product Title</label>
                                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($p['title']) ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Price (ZAR)</label>
                                            <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($p['price']) ?>" required>
                                        </div>
                                        <div class="col-md-3 standard-edit-quantity">
                                            <label>Quantity</label>
                                            <input type="number" min="1" name="quantity" class="form-control" value="<?= (int)($p['quantity'] ?? 1) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Category</label>
                                            <select name="category" class="form-select edit-category-select">
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['cat_id'] ?>" data-is-clothing="<?= isClothingCategoryName($cat['name']) ? '1' : '0' ?>" <?= (int)$p['cat_id'] === (int)$cat['cat_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cat['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Location</label>
                                            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($p['location']) ?>" required>
                                        </div>
                                        <div class="col-12 edit-clothing-sizes d-none">
                                            <label>Clothing size quantities</label>
                                            <div class="row g-2">
                                                <?php foreach (clothingSizes() as $size): ?>
                                                    <div class="col-6 col-md-2">
                                                        <label class="form-label text-uppercase small"><?= htmlspecialchars($size) ?></label>
                                                        <input type="number" min="0" name="sizes[<?= htmlspecialchars($size) ?>]" class="form-control" value="<?= (int)$editSizeStock[$size] ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label>Description</label>
                                            <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($p['description']) ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label>Replace Product Image</label>
                                            <input type="file" name="image" class="form-control" accept="image/*">
                                            <?php if (!empty($p['image_url'])): ?>
                                                <small class="text-muted">Current image: <?= htmlspecialchars($p['image_url']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success me-2">
                                                <i class="bi bi-check-circle me-1"></i>Save Changes
                                            </button>
                                            <button type="button" class="btn btn-secondary edit-toggle" data-edit-target="edit-product-<?= $p['prod_id'] ?>">Cancel</button>
                                        </div>
                                    </div>
                                    <div class="alert alert-info mt-3 mb-0">
                                        Saving changes sends this product back to admin for approval before it appears in the marketplace again.
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">You haven’t listed any products yet.</div>
        <?php endif; ?>
    </div>
    <script>
        const categorySelect = document.getElementById('categorySelect');
        const standardQuantityGroup = document.getElementById('standardQuantityGroup');
        const clothingSizesGroup = document.getElementById('clothingSizesGroup');
        const standardQuantityInput = standardQuantityGroup.querySelector('input[name="quantity"]');

        function toggleClothingSizes() {
            const selected = categorySelect.options[categorySelect.selectedIndex];
            const isClothing = selected?.dataset.isClothing === '1';
            clothingSizesGroup.classList.toggle('d-none', !isClothing);
            standardQuantityGroup.classList.toggle('d-none', isClothing);
            standardQuantityInput.required = !isClothing;
        }

        categorySelect.addEventListener('change', toggleClothingSizes);
        toggleClothingSizes();

        document.querySelectorAll('.edit-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const row = document.getElementById(button.dataset.editTarget);
                if (row) {
                    row.classList.toggle('d-none');
                }
            });
        });

        document.querySelectorAll('.edit-product-form').forEach((form) => {
            const editCategorySelect = form.querySelector('.edit-category-select');
            const editStandardQuantity = form.querySelector('.standard-edit-quantity');
            const editStandardQuantityInput = editStandardQuantity.querySelector('input[name="quantity"]');
            const editClothingSizes = form.querySelector('.edit-clothing-sizes');

            function toggleEditClothingSizes() {
                const selected = editCategorySelect.options[editCategorySelect.selectedIndex];
                const isClothing = selected?.dataset.isClothing === '1';
                editClothingSizes.classList.toggle('d-none', !isClothing);
                editStandardQuantity.classList.toggle('d-none', isClothing);
                editStandardQuantityInput.required = !isClothing;
            }

            editCategorySelect.addEventListener('change', toggleEditClothingSizes);
            toggleEditClothingSizes();
        });
    </script>
</body>
</html>
