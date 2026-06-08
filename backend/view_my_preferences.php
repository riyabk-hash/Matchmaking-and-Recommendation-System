<?php
session_start();
require_once '../config/db_config.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT username, email, preferred_categories, price_range_min, price_range_max, preferred_location, style_preference, size_preference, age_group FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $preferred_categories = json_decode($user['preferred_categories'], true) ?? [];
    $user['preferred_categories_display'] = $preferred_categories ? implode(', ', array_map('ucfirst', $preferred_categories)) : 'Not set';
} else {
    die('User not found.');
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Preferences - Thriftic</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            background: var(--bg-primary);
            font-family: var(--font);
            margin: 0;
            padding: 0;
        }
        .pref-page-container {
            max-width: 700px;
            margin: 60px auto;
            padding: 0 20px;
        }
        .pref-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .pref-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 8px;
        }
        .pref-header p {
            color: var(--text-muted);
            font-size: 15px;
            margin: 0;
        }
        .pref-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .pref-row {
            display: flex;
            align-items: center;
            padding: 20px 28px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .pref-row:last-child {
            border-bottom: none;
        }
        .pref-row:hover {
            background: var(--bg-primary);
        }
        .pref-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            flex-shrink: 0;
            color: var(--accent);
            font-size: 18px;
        }
        .pref-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .pref-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
        }
        .pref-value.not-set {
            color: var(--text-muted);
            font-style: italic;
            font-weight: 400;
        }
        .pref-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 32px;
        }
        .pref-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-edit {
            background: var(--accent);
            color: #fff;
        }
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-back {
            background: var(--bg-secondary);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-back:hover {
            background: var(--bg-primary);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="pref-page-container">
        <div class="pref-header">
            <h1><i class="fa-solid fa-sliders"></i> My Preferences</h1>
            <p>Your personalized settings that power smarter recommendations.</p>
        </div>

        <div class="pref-card">
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div class="pref-label">Username</div>
                    <div class="pref-value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
            </div>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-envelope"></i></div>
                <div>
                    <div class="pref-label">Email</div>
                    <div class="pref-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
            </div>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-tags"></i></div>
                <div>
                    <div class="pref-label">Preferred Categories</div>
                    <div class="pref-value <?php echo ($user['preferred_categories_display'] === 'Not set') ? 'not-set' : ''; ?>">
                        <?php echo htmlspecialchars($user['preferred_categories_display']); ?>
                    </div>
                </div>
            </div>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-wallet"></i></div>
                <div>
                    <div class="pref-label">Price Range</div>
                    <div class="pref-value">
                        Rs. <?php echo $user['price_range_min'] ?? 0; ?> — Rs. <?php echo $user['price_range_max'] ?? 'N/A'; ?>
                    </div>
                </div>
            </div>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-location-dot"></i></div>
                <div>
                    <div class="pref-label">Preferred Location</div>
                    <div class="pref-value <?php echo empty($user['preferred_location']) ? 'not-set' : ''; ?>">
                        <?php echo htmlspecialchars($user['preferred_location'] ?? 'Not set'); ?>
                    </div>
                </div>
            </div>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-shirt"></i></div>
                <div>
                    <div class="pref-label">Style Preference</div>
                    <div class="pref-value <?php echo empty($user['style_preference']) ? 'not-set' : ''; ?>">
                        <?php echo htmlspecialchars(ucfirst($user['style_preference'] ?? 'Not set')); ?>
                    </div>
                </div>
            </div>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-ruler"></i></div>
                <div>
                    <div class="pref-label">Size Preference</div>
                    <div class="pref-value <?php echo empty($user['size_preference']) ? 'not-set' : ''; ?>">
                        <?php echo htmlspecialchars(strtoupper($user['size_preference'] ?? 'Not set')); ?>
                    </div>
                </div>
            </div>
            <?php if (isset($user['age_group'])): ?>
            <div class="pref-row">
                <div class="pref-icon"><i class="fa-solid fa-cake-candles"></i></div>
                <div>
                    <div class="pref-label">Age Group</div>
                    <div class="pref-value <?php echo empty($user['age_group']) ? 'not-set' : ''; ?>">
                        <?php echo htmlspecialchars(ucfirst($user['age_group'] ?? 'Not set')); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="pref-actions">
            <a href="../preferences.html" class="btn-edit"><i class="fa-solid fa-pen"></i> Edit Preferences</a>
            <a href="../home.html" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</body>
</html>
