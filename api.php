<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Action: Mark Notification as Read / Mark All Read (Accepts GET or POST)
if ($action === 'mark_notif_read') {
    if (!isset($_SESSION['read_notifications'])) {
        $_SESSION['read_notifications'] = [];
    }

    $notif_id = trim($_REQUEST['notif_id'] ?? '');
    $mark_all = ($_REQUEST['mark_all'] ?? '') === '1';

    if ($mark_all) {
        $all_notifs = get_live_notifications(30);
        foreach ($all_notifs as $n) {
            if (!in_array($n['id'], $_SESSION['read_notifications'])) {
                $_SESSION['read_notifications'][] = $n['id'];
            }
        }
    } else if (!empty($notif_id)) {
        if (!in_array($notif_id, $_SESSION['read_notifications'])) {
            $_SESSION['read_notifications'][] = $notif_id;
        }
    }

    $fresh = get_live_notifications(30);
    $unread_count = 0;
    foreach ($fresh as $f) {
        if (!$f['is_read']) {
            $unread_count++;
        }
    }

    echo json_encode(['success' => true, 'unread_count' => $unread_count]);
    exit;
}

// Action: Live Search Suggestions Auto-Complete
if ($action === 'search_suggestions') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    $db = get_db();
    $param = '%' . $q . '%';
    $stmt = $db->prepare("
        SELECT n.id, n.title, n.slug, n.image_url, c.name AS category_name
        FROM news n
        JOIN categories c ON n.category_id = c.id
        WHERE (n.title LIKE ? OR n.content LIKE ? OR n.excerpt LIKE ?) AND n.status = 'published'
        ORDER BY n.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$param, $param, $param]);
    $results = $stmt->fetchAll();

    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();

    // Action 1: Article Toggle Like/Unlike (1 Like Max Per Session: +1 or -1)
    if ($action === 'like' || $action === 'like_article') {
        $news_id = (int)($_REQUEST['news_id'] ?? 0);

        if ($news_id > 0) {
            if (!isset($_SESSION['liked_articles'])) {
                $_SESSION['liked_articles'] = [];
            }

            try {
                $is_currently_liked = !empty($_SESSION['liked_articles'][$news_id]);

                if ($is_currently_liked) {
                    // Unlike: decrement count (-1)
                    $stmt = $db->prepare("UPDATE news SET likes = CASE WHEN likes > 0 THEN likes - 1 ELSE 0 END WHERE id = ?");
                    $stmt->execute([$news_id]);
                    unset($_SESSION['liked_articles'][$news_id]);
                    $is_liked = false;
                } else {
                    // Like: increment count (+1)
                    $stmt = $db->prepare("UPDATE news SET likes = likes + 1 WHERE id = ?");
                    $stmt->execute([$news_id]);
                    $_SESSION['liked_articles'][$news_id] = true;
                    $is_liked = true;
                }

                $fetch = $db->prepare("SELECT likes FROM news WHERE id = ?");
                $fetch->execute([$news_id]);
                $likes = (int)$fetch->fetchColumn();

                echo json_encode([
                    'success' => true, 
                    'is_liked' => $is_liked, 
                    'likes' => $likes, 
                    'new_likes' => number_format($likes)
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
    }

    // Action 2: Post Reader Comment
    if ($action === 'comment') {
        $news_id = (int)($_POST['news_id'] ?? 0);
        $author_name = trim($_POST['author_name'] ?? '');
        $author_email = trim($_POST['author_email'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($news_id > 0 && !empty($author_name) && !empty($content)) {
            try {
                $stmt = $db->prepare("INSERT INTO comments (news_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, 'approved')");
                $stmt->execute([$news_id, $author_name, $author_email, $content]);

                echo json_encode([
                    'success' => true,
                    'comment' => [
                        'author_name' => sanitize($author_name),
                        'content' => sanitize($content),
                        'created_at' => 'Just now'
                    ]
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to record comment.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Name and comment text are required.']);
            exit;
        }
    }

    // Action 3: Newsletter Subscriber Registration
    if ($action === 'subscribe') {
        $email = trim($_POST['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = $db->prepare("INSERT INTO subscribers (email) VALUES (?)");
                $stmt->execute([$email]);
                echo json_encode(['success' => true, 'message' => 'Thank you for subscribing to CPL News alerts!']);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => true, 'message' => 'You are already subscribed to CPL News alerts.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action request.']);
exit;
