<?php
require_once __DIR__ . '/includes/config.php';
$db = get_db();

$news_id = (int)($_GET['id'] ?? 0);
if ($news_id <= 0) {
    header('Location: index.php');
    exit;
}

// Increment View Counter
$update_views = $db->prepare("UPDATE news SET views = views + 1 WHERE id = ?");
$update_views->execute([$news_id]);

// Fetch Article Details with Author & Category
$stmt = $db->prepare("
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
           a.full_name AS author_name, a.avatar_url AS author_avatar, a.role AS author_role
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    LEFT JOIN admins a ON n.author_id = a.id
    WHERE n.id = ? AND n.status = 'published'
");
$stmt->execute([$news_id]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: index.php');
    exit;
}

// Canonical & OpenGraph Preview Metadata for Social Sharing
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_domain_url = $protocol . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$current_url = $protocol . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '');

$raw_img = $article['image_url'] ?? '';
if (!empty($raw_img)) {
    if (strpos($raw_img, 'http://') === 0 || strpos($raw_img, 'https://') === 0) {
        $og_image = $raw_img;
    } else {
        $og_image = $base_domain_url . '/' . ltrim($raw_img, '/');
    }
} else {
    $og_image = 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=1200&q=80';
}

$og_title = $article['title'];
$og_desc  = !empty($article['excerpt']) ? $article['excerpt'] : short_text(strip_tags($article['content']), 160);
$og_url   = $current_url;
$og_type  = 'article';

$page_title = $article['title'];
$meta_desc  = $og_desc;
$meta_keys  = $article['meta_keywords'] ?? 'actualités, cpl news, ' . ($article['category_name'] ?? '');

require_once __DIR__ . '/includes/header.php';

// Fetch Approved Comments
$comm_stmt = $db->prepare("SELECT * FROM comments WHERE news_id = ? AND status = 'approved' ORDER BY created_at DESC");
$comm_stmt->execute([$news_id]);
$comments = $comm_stmt->fetchAll();

// Fetch Related Articles
$rel_stmt = $db->prepare("
    SELECT n.*, c.name AS category_name, c.color_code 
    FROM news n 
    JOIN categories c ON n.category_id = c.id
    WHERE n.category_id = ? AND n.id != ? AND n.status = 'published'
    ORDER BY n.published_at DESC 
    LIMIT 4
");
$rel_stmt->execute([$article['category_id'], $news_id]);
$related = $rel_stmt->fetchAll();

// Fetch Trending Stories for Sidebar
$trend_stmt = $db->query("
    SELECT n.*, c.name AS category_name, c.color_code 
    FROM news n 
    JOIN categories c ON n.category_id = c.id
    WHERE n.status = 'published'
    ORDER BY n.views DESC 
    LIMIT 5
");
$trending_sidebar = $trend_stmt ? $trend_stmt->fetchAll() : [];

$is_user_liked = !empty($_SESSION['liked_articles'][$news_id]);
?>

<style>
  /* ===== SINGLE ARTICLE DETAIL PAGE NATIVE APP DESIGN SYSTEM ===== */
  .article-page-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2.25rem;
    margin-bottom: 3.5rem;
  }
  @media (min-width: 960px) {
    .article-page-layout { grid-template-columns: 2.4fr 1fr; }
  }

    /* App-Native Breadcrumbs Navigation */
  .article-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: var(--muted);
    margin-bottom: 1rem;
    flex-wrap: wrap;
    font-family: var(--font-display);
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 6px 14px;
    border-radius: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
  }
  .article-breadcrumb a {
    color: var(--text);
    text-decoration: none;
    font-weight: 700;
    transition: color 0.15s ease;
  }
  .article-breadcrumb a:hover { color: var(--red); }
  .article-breadcrumb-sep { color: var(--border); font-size: 0.68rem; }

  /* Unified Article Header Card Frame */
  .article-header-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.75rem 1.75rem 1.25rem 1.75rem;
    margin-bottom: 1.75rem;
    box-shadow: var(--shadow-s);
  }
  @media (max-width: 768px) {
    .article-header-box {
      padding: 1.15rem 1.15rem 0.95rem 1.15rem;
      margin-bottom: 1.25rem;
      border-radius: 16px;
    }
  }

  .article-title-main {
    font-family: var(--font-serif);
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1.2;
    color: var(--text);
    margin: 0.5rem 0 1rem 0;
    letter-spacing: -0.02em;
  }
  @media (max-width: 640px) {
    .article-title-main { font-size: 1.4rem; line-height: 1.25; margin: 0.35rem 0 0.85rem 0; }
  }

  .article-subtitle-text {
    font-family: var(--font-display);
    color: #6366f1;
    font-weight: 800;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin: 0.65rem 0 0.25rem 0;
  }
  [data-theme="dark"] .article-subtitle-text {
    color: #818cf8;
  }

  .article-byline-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    margin-top: 1.1rem;
  }

  /* Social Sharing Bar */
  .social-share-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.75rem;
  }
  .share-label {
    font-family: var(--font-display);
    font-size: 0.78rem;
    font-weight: 900;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-right: 0.25rem;
  }
  .share-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.95rem;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 800;
    font-family: var(--font-display);
    color: #ffffff;
    text-decoration: none;
    transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
  }
  .share-btn:hover { transform: translateY(-2px); opacity: 0.92; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
  .btn-share-native { background: linear-gradient(135deg, var(--red), var(--red-dk)); cursor: pointer; border: none; }
  .btn-share-wa { background: #25d366; }
  .btn-share-tg { background: #0088cc; }
  .btn-share-fb { background: #1877f2; }
  .btn-share-tw { background: #000000; }
  .btn-share-li { background: #0a66c2; }
  .btn-share-copy {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    cursor: pointer;
  }

  /* Article Media Container */
  .article-media-wrap {
    background: #090d16;
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.75rem;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    border: 1px solid var(--border);
  }
  .article-main-img {
    width: 100%;
    max-height: 540px;
    object-fit: cover;
    display: block;
  }
  .article-media-caption {
    padding: 0.75rem 1.1rem;
    background: var(--surface);
    color: var(--muted);
    font-size: 0.82rem;
    border-top: 1px solid var(--border);
    font-style: italic;
    display: flex;
    align-items: center;
    gap: 0.4rem;
  }
  .article-video-player {
    width: 100%;
    max-height: 480px;
    display: block;
    outline: none;
  }
  .article-media-wrap iframe {
    width: 100%;
    aspect-ratio: 16 / 9;
    height: auto;
    border: none;
    display: block;
  }

  /* Article Excerpt & Body Typography */
  .article-lead-excerpt {
    font-size: 1.18rem;
    font-weight: 600;
    line-height: 1.65;
    color: var(--text);
    border-left: 4px solid var(--red);
    padding: 1.1rem 1.35rem;
    margin-bottom: 2rem;
    background: var(--surface);
    border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
    box-shadow: var(--shadow-s);
  }

  .article-content-body {
    font-family: var(--font-body);
    font-size: 1.06rem;
    line-height: 1.85;
    color: var(--text);
    margin-bottom: 2.5rem;
  }
  .article-content-body p {
    margin-bottom: 1.35rem;
  }

  /* Interactive Support/Like Action Card */
  .article-like-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 2.75rem;
    box-shadow: var(--shadow-s);
    flex-wrap: wrap;
  }
  .like-card-title {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: 1.05rem;
    color: var(--text);
    margin-bottom: 3px;
  }
  .like-card-sub {
    font-size: 0.82rem;
    color: var(--muted);
  }
  .like-main-btn {
    background: #1877f2;
    color: #ffffff;
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 0.92rem;
    padding: 0.75rem 1.6rem;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 0.55rem;
    box-shadow: 0 6px 20px rgba(24, 119, 242, 0.4);
  }
  .like-main-btn:hover {
    background: #166fe5;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(24, 119, 242, 0.5);
  }
  .like-main-btn.liked {
    background: #166fe5;
    transform: scale(1.04);
  }

  /* Reader Discussion / Comments Section */
  .comments-wrapper {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    box-shadow: var(--shadow-s);
  }
  .comments-head-title {
    font-family: var(--font-display);
    font-size: 1.2rem;
    font-weight: 900;
    color: var(--text);
    margin-bottom: 1.25rem;
    border-bottom: 2px solid var(--red);
    padding-bottom: 0.55rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .comment-input-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.85rem;
    margin-bottom: 1rem;
  }
  @media (min-width: 640px) {
    .comment-input-grid { grid-template-columns: 1fr 1fr; }
  }

  .comment-input-field {
    width: 100%;
    font-family: var(--font-body);
    font-size: 0.88rem;
    padding: 0.75rem 0.95rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg);
    color: var(--text);
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .comment-input-field:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12);
  }

  .comment-submit-btn {
    background: var(--red);
    color: #ffffff;
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 0.85rem;
    padding: 0.75rem 1.6rem;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.15s ease;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.3);
  }
  .comment-submit-btn:hover { background: var(--red-dk); transform: translateY(-1px); }

  .comment-list-box {
    margin-top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  .comment-card-item {
    padding: 1.1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }
  .comment-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.45rem;
  }
  .comment-author-name {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 0.92rem;
    color: var(--text);
  }
  .comment-time-badge {
    font-size: 0.74rem;
    color: var(--dim);
    font-family: var(--font-display);
  }
  .comment-card-body {
    font-size: 0.88rem;
    color: var(--muted);
    line-height: 1.55;
  }

  /* Right Sidebar Panel */
  .article-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
  }
  .sidebar-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    box-shadow: var(--shadow-s);
  }
  .sidebar-head-t {
    font-family: var(--font-display);
    font-size: 0.95rem;
    font-weight: 900;
    text-transform: uppercase;
    color: var(--text);
    border-bottom: 2px solid var(--red);
    padding-bottom: 0.55rem;
    margin-bottom: 0.85rem;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }
  .rel-item-card {
    display: flex;
    gap: 0.85rem;
    align-items: center;
    padding: 0.7rem 0;
    border-bottom: 1px solid var(--border);
    text-decoration: none;
    transition: transform 0.15s ease;
  }
  .rel-item-card:last-child { border-bottom: none; }
  .rel-item-card:hover { transform: translateX(3px); }
  .rel-thumb {
    width: 76px; height: 58px;
    border-radius: 6px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid var(--border);
    background: #0f172a;
  }
  .rel-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.85rem;
    line-height: 1.35;
    color: var(--text);
    transition: color 0.15s ease;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .rel-item-card:hover .rel-title { color: var(--red); }

  /* Reading Progress Indicator Bar */
  .reading-progress-bar {
    position: fixed;
    top: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--red), #f97316);
    width: 0%;
    z-index: 100001;
    transition: width 0.1s ease-out;
  }

  /* Mobile App Back To Top Floating Action Button */
  .back-to-top-btn {
    position: fixed;
    bottom: 82px;
    right: 18px;
    z-index: 9999;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
  }
  .back-to-top-btn.visible { opacity: 1; visibility: visible; }
  .back-to-top-btn:hover { background: var(--red); color: #fff; border-color: var(--red); }

  /* ===== MOBILE RESPONSIVE LAYOUT ENHANCEMENTS ===== */
  @media (max-width: 768px) {
    .article-page-layout { gap: 1.5rem; margin-bottom: 2rem; }
    .article-title-main { font-size: 1.5rem; line-height: 1.25; margin-bottom: 0.75rem; letter-spacing: -0.3px; }
    .article-byline-bar { flex-direction: column; align-items: flex-start; gap: 0.75rem; padding: 0.85rem 0; }
    .author-info-flex { width: 100%; }
    .article-stats-list { width: 100%; justify-content: flex-start; gap: 0.45rem; flex-wrap: wrap; }
    .social-share-bar { gap: 0.4rem; }
    .share-btn { font-size: 0.72rem; padding: 0.4rem 0.7rem; }
    .article-main-img, .article-video-player { max-height: 250px; }
    .article-media-wrap { border-radius: var(--radius); margin-bottom: 1.25rem; }
    .article-lead-excerpt { font-size: 0.98rem; padding: 0.75rem 0.9rem; line-height: 1.6; }
    .article-content-body { font-size: 1rem; line-height: 1.75; margin-bottom: 1.75rem; }
    .article-like-card { flex-direction: column; align-items: stretch; text-align: center; gap: 0.85rem; padding: 1.1rem; }
    .like-main-btn { width: 100%; justify-content: center; }
    .comments-wrapper { padding: 1.1rem; }
    .comments-head-title { font-size: 1.1rem; }
    .sidebar-box { padding: 1.1rem; }
  }
</style>

<!-- Reading Progress Bar -->
<div id="readingProgressBar" class="reading-progress-bar"></div>

<!-- Back To Top Floating Action Button -->
<button type="button" id="backToTopBtn" class="back-to-top-btn" aria-label="Retour en haut" onclick="scrollToTop()">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
</button>

<!-- Fil d'ariane (Breadcrumb Navigation) -->
<nav class="article-breadcrumb" aria-label="Fil d'ariane">
  <a href="index.php">Accueil</a>
  <span class="article-breadcrumb-sep">›</span>
  <a href="category.php?slug=<?php echo urlencode($article['category_slug']); ?>"><?php echo sanitize($article['category_name']); ?></a>
</nav>

<div class="article-page-layout">
  
  <!-- Left Main Column: Full Article View -->
  <main>
    <article class="article-header-box">
      
      <!-- Category & Status Badges Bar -->
      <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
        <span class="cat-badge" style="background:<?php echo sanitize($article['color_code'] ?: 'var(--red)'); ?>">
          <span style="width:6px;height:6px;border-radius:50%;background:#fff"></span>
          <?php echo sanitize($article['category_name']); ?>
        </span>
        <?php if (!empty($article['is_breaking'])): ?>
          <span class="cat-badge" style="background:var(--red)">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            BREAKING NEWS
          </span>
        <?php endif; ?>
        <?php if (!empty($article['video_url'])): ?>
          <span class="cat-badge" style="background:var(--blue)">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            GRAND REPORTAGE VIDÉO
          </span>
        <?php endif; ?>
      </div>

      <?php if (!empty($article['sub_title'])): ?>
        <?php 
          // Clean raw emojis and duplicate "BREAKING:" prefixes from kicker
          $clean_sub = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $article['sub_title']);
          $clean_sub = trim(preg_replace('/^BREAKING\s*:\s*/i', '', $clean_sub));
        ?>
        <?php if (!empty($clean_sub)): ?>
          <div class="article-subtitle-text">
            <?php echo sanitize($clean_sub); ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <h1 class="article-title-main"><?php echo sanitize($article['title']); ?></h1>
      
      <!-- Byline Metadata Bar -->
      <div class="article-byline-bar">
        <div class="author-info-flex">
          <?php if (!empty($article['author_avatar'])): ?>
            <img src="<?php echo sanitize($article['author_avatar']); ?>" class="author-avatar-img" alt="<?php echo sanitize($article['author_name']); ?>">
          <?php else: ?>
            <div class="author-avatar-fallback">
              <?php echo strtoupper(substr($article['author_name'] ?: 'C', 0, 1)); ?>
            </div>
          <?php endif; ?>
          <div>
            <div class="author-meta-name">Par <?php echo sanitize($article['author_name'] ?: 'Rédaction CPL NEWS'); ?></div>
            <div class="author-meta-date">Publié <?php echo time_ago($article['published_at']); ?> • <?php echo date('d/m/Y \à H:i', strtotime($article['published_at'])); ?></div>
          </div>
        </div>

        <div class="article-stats-list">
          <span class="article-stat-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php echo $article['read_time_minutes']; ?> min de lecture
          </span>
          <span class="article-stat-badge">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?php echo number_format($article['views']); ?> vues
          </span>
          <span class="article-stat-badge" style="color:#1877f2">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3v11z"/></svg> 
            <span id="articleLikeCountHeader"><?php echo number_format($article['likes']); ?></span> j'aime
          </span>
        </div>
      </div>
    </article>


      <!-- Social Sharing Bar (Universal Mobile & Desktop Support) -->
      <div class="social-share-bar">
        <span class="share-label">Partager cet article :</span>
        
        <!-- Native Device Share Sheet (Auto-active on mobile phones) -->
        <button type="button" id="nativeShareBtn" class="share-btn btn-share-native" onclick="shareStoryNative()" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
          Partager
        </button>

        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($article['title'] . "\n" . $current_url); ?>" target="_blank" rel="noopener" class="share-btn btn-share-wa" title="Partager sur WhatsApp">
          WhatsApp
        </a>
        <a href="https://t.me/share/url?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($article['title']); ?>" target="_blank" rel="noopener" class="share-btn btn-share-tg" title="Partager sur Telegram">
          Telegram
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener" class="share-btn btn-share-fb" title="Partager sur Facebook">
          Facebook
        </a>
        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($article['title']); ?>&url=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener" class="share-btn btn-share-tw" title="Partager sur X / Twitter">
          X
        </a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($current_url); ?>" target="_blank" rel="noopener" class="share-btn btn-share-li" title="Partager sur LinkedIn">
          LinkedIn
        </a>
        <button type="button" id="copyLinkBtn" class="share-btn btn-share-copy" onclick="copyArticleLink()" title="Copier le lien">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          Copier le lien
        </button>
      </div>

      <!-- Media Banner Box (Video Player or Cover Image) -->
      <?php if (!empty($article['video_url'])): ?>
        <div class="article-media-wrap">
          <?php if (strpos($article['video_url'], 'youtube.com') !== false || strpos($article['video_url'], 'youtu.be') !== false): ?>
            <?php $embed_v = str_replace(['watch?v=', 'youtu.be/'], ['embed/', 'youtube.com/embed/'], $article['video_url']); ?>
            <iframe src="<?php echo sanitize($embed_v); ?>" width="100%" height="450" frameborder="0" allowfullscreen></iframe>
          <?php else: ?>
            <video controls controlsList="nodownload" class="article-video-player" poster="<?php echo sanitize($article['image_url'] ?: ''); ?>">
              <source src="<?php echo sanitize($article['video_url']); ?>">
              Votre navigateur ne prend pas en charge la lecture vidéo HTML5.
            </video>
          <?php endif; ?>
        </div>
      <?php elseif (!empty($article['image_url'])): ?>
        <div class="article-media-wrap">
          <img src="<?php echo sanitize($article['image_url']); ?>" alt="<?php echo sanitize($article['title']); ?>" class="article-main-img">
          <?php if (!empty($article['image_caption'])): ?>
            <div class="article-media-caption">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              Crédit photo : <?php echo sanitize($article['image_caption']); ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Article Excerpt Lead -->
      <?php if (!empty($article['excerpt'])): ?>
        <div class="article-lead-excerpt">
          <?php echo sanitize($article['excerpt']); ?>
        </div>
      <?php endif; ?>

      <!-- Main Article Body -->
      <div class="article-content-body">
        <?php echo nl2br(sanitize($article['content'])); ?>
      </div>

      <!-- Article Reader Like Action Box -->
      <div class="article-like-card">
        <div>
          <div class="like-card-title">Soutenez le journalisme d'investigation</div>
          <div class="like-card-sub">Si vous avez apprécié cette enquête, aimez cet article pour le faire monter dans le classement.</div>
        </div>
        <button type="button" id="likeArticleBtn" class="like-main-btn <?php echo $is_user_liked ? 'liked' : ''; ?>" onclick="likeMainArticle(<?php echo $article['id']; ?>)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3v11z"/></svg> 
          <span id="likeBtnLabel"><?php echo $is_user_liked ? "J'aime déjà" : "J'aime"; ?></span> (<span id="articleLikeCountBody"><?php echo number_format($article['likes']); ?></span>)
        </button>
      </div>

    </article>

    <!-- Reader Discussion / Comments Section -->
    <section class="comments-wrapper" id="commentsSection">
      <h3 class="comments-head-title" id="commentsCountHeader">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Espace de discussion (<?php echo count($comments); ?>)
      </h3>

      <!-- AJAX Comment Submission Form -->
      <form id="articleCommentForm">
        <input type="hidden" name="news_id" value="<?php echo $article['id']; ?>">
        
        <div class="comment-input-grid">
          <input type="text" name="author_name" class="comment-input-field" placeholder="Votre nom complet *" required>
          <input type="email" name="author_email" class="comment-input-field" placeholder="Votre adresse email (Optionnel)">
        </div>

        <div style="margin-bottom:1rem">
          <textarea name="content" class="comment-input-field" rows="4" placeholder="Partagez votre opinion sur cet article..." required></textarea>
        </div>

        <button type="submit" class="comment-submit-btn">Publier mon commentaire</button>
        <div id="commentFormStatus" style="font-size:0.82rem;font-weight:700;display:none;margin-top:0.65rem"></div>
      </form>

      <!-- Comments List -->
      <div id="commentsList" class="comment-list-box">
        <?php if (empty($comments)): ?>
          <p id="noCommentsMsg" style="color:var(--muted);font-size:0.88rem;text-align:center;padding:1.2rem 0">
            Aucun commentaire pour le moment. Soyez le premier à exprimer votre opinion !
          </p>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment-card-item">
              <div class="comment-card-header">
                <span class="comment-author-name"><?php echo sanitize($c['author_name']); ?></span>
                <span class="comment-time-badge"><?php echo time_ago($c['created_at']); ?></span>
              </div>
              <div class="comment-card-body">
                <?php echo nl2br(sanitize($c['content'])); ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- Right Sidebar Column (Related Stories & Trending Ranking) -->
  <aside class="article-sidebar">
    
    <!-- Related Category Stories -->
    <div class="sidebar-box">
      <div class="sidebar-head-t">
        <span style="width:8px;height:8px;border-radius:50%;background:var(--red)"></span>
        Sur le même sujet
      </div>
      
      <?php if (empty($related)): ?>
        <p style="color:var(--muted);font-size:0.84rem">Aucun article connexe dans cette rubrique.</p>
      <?php else: ?>
        <?php foreach ($related as $rel): ?>
          <a href="article.php?id=<?php echo $rel['id']; ?>&slug=<?php echo urlencode($rel['slug']); ?>" class="rel-item-card">
            <?php $rel_img = !empty($rel['image_url']) ? $rel['image_url'] : 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=150&q=80'; ?>
            <img src="<?php echo sanitize($rel_img); ?>" alt="" class="rel-thumb" loading="lazy">
            
            <div style="flex:1;min-width:0">
              <h4 class="rel-title"><?php echo sanitize($rel['title']); ?></h4>
              <div style="font-size:0.7rem;color:var(--dim);margin-top:3px;display:flex;align-items:center;gap:0.4rem">
                <span>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?php echo $rel['read_time_minutes']; ?> min
                </span>
                <span>•</span>
                <span>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <?php echo number_format($rel['views']); ?>
                </span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Trending Most Read Stories -->
    <?php if (!empty($trending_sidebar)): ?>
      <div class="sidebar-box">
        <div class="sidebar-head-t">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          Les plus lus
        </div>
        
        <?php foreach ($trending_sidebar as $idx => $ts): ?>
          <a href="article.php?id=<?php echo $ts['id']; ?>&slug=<?php echo urlencode($ts['slug']); ?>" class="rel-item-card">
            <span style="font-family:var(--font-display);font-size:1.15rem;font-weight:900;color:var(--red);width:22px;flex-shrink:0">
              0<?php echo $idx + 1; ?>
            </span>
            <div style="flex:1;min-width:0">
              <span style="font-size:0.66rem;font-weight:800;color:<?php echo sanitize($ts['color_code'] ?: 'var(--red)'); ?>;text-transform:uppercase;letter-spacing:0.5px">
                <?php echo sanitize($ts['category_name']); ?>
              </span>
              <h4 class="rel-title"><?php echo sanitize(short_text($ts['title'], 52)); ?></h4>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </aside>

</div>

<!-- Interactive Scripts for Article Page -->
<script>
// Toggle Add/Remove Like Engine (1 Like Max per session: +1 or -1)
function likeMainArticle(newsId) {
  const btn = document.getElementById('likeArticleBtn');
  const hCnt = document.getElementById('articleLikeCountHeader');
  const bCnt = document.getElementById('articleLikeCountBody');
  const btnLabel = document.getElementById('likeBtnLabel');
  
  const isLikedNow = btn ? btn.classList.contains('liked') : false;
  let currentVal = 0;
  if (bCnt) {
    currentVal = parseInt(bCnt.textContent.replace(/,/g, '')) || 0;
  }
  
  // 1. INSTANT 0ms Optimistic UI Toggle Update
  let optimisticVal = isLikedNow ? Math.max(0, currentVal - 1) : currentVal + 1;
  let formattedOpt = optimisticVal.toLocaleString();

  if (hCnt) hCnt.textContent = formattedOpt;
  if (bCnt) bCnt.textContent = formattedOpt;
  
  if (btn) {
    if (isLikedNow) {
      btn.classList.remove('liked');
      if (btnLabel) btnLabel.textContent = "J'aime";
    } else {
      btn.classList.add('liked');
      if (btnLabel) btnLabel.textContent = "J'aime déjà";
    }
  }

  // 2. Async background sync with server session
  fetch('api.php?action=like_article&news_id=' + newsId, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const serverLikes = d.new_likes || (d.likes !== undefined ? d.likes.toLocaleString() : null);
        if (serverLikes !== null) {
          if (hCnt) hCnt.textContent = serverLikes;
          if (bCnt) bCnt.textContent = serverLikes;
        }
        if (btn) {
          if (d.is_liked) {
            btn.classList.add('liked');
            if (btnLabel) btnLabel.textContent = "J'aime déjà";
          } else {
            btn.classList.remove('liked');
            if (btnLabel) btnLabel.textContent = "J'aime";
          }
        }
      }
    });
}

// AJAX Reader Discussion Form Submission Engine
document.addEventListener('DOMContentLoaded', () => {
  const cForm = document.getElementById('articleCommentForm');
  const cStatus = document.getElementById('commentFormStatus');
  const cList = document.getElementById('commentsList');

  if (cForm) {
    cForm.addEventListener('submit', (e) => {
      e.preventDefault();
      cStatus.style.display = 'block';
      cStatus.style.color = 'var(--muted)';
      cStatus.textContent = 'Envoi de votre commentaire...';

      const formData = new FormData(cForm);

      fetch('api.php?action=comment', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            cStatus.style.color = '#10b981';
            cStatus.textContent = d.message || 'Commentaire publié avec succès !';
            
            const noMsg = document.getElementById('noCommentsMsg');
            if (noMsg) noMsg.remove();

            const author = formData.get('author_name');
            const text = formData.get('content');

            const newCard = document.createElement('div');
            newCard.className = 'comment-card-item';
            newCard.innerHTML = `
              <div class="comment-card-header">
                <span class="comment-author-name">${author}</span>
                <span class="comment-time-badge">À l'instant</span>
              </div>
              <div class="comment-card-body">${text}</div>
            `;
            cList.prepend(newCard);
            cForm.reset();
          } else {
            cStatus.style.color = '#ef4444';
            cStatus.textContent = d.error || 'Erreur lors de l\'envoi du commentaire.';
          }
        })
        .catch(() => {
          cStatus.style.color = '#ef4444';
          cStatus.textContent = 'Erreur de connexion réseau.';
        });
    });
  }
});

// Universal Story Link Copy & Toast Feedback Handler
function copyArticleLink() {
  const url = window.location.href;
  const btn = document.getElementById('copyLinkBtn');
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(() => {
      showCopyFeedback(btn);
    }).catch(() => {
      fallbackCopyText(url, btn);
    });
  } else {
    fallbackCopyText(url, btn);
  }
}

function fallbackCopyText(text, btn) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  try {
    document.execCommand('copy');
    showCopyFeedback(btn);
  } catch (err) {
    prompt('Copiez le lien de cet article :', text);
  }
  document.body.removeChild(ta);
}

function showCopyFeedback(btn) {
  if (!btn) return;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Lien copié !';
  btn.style.background = '#10b981';
  btn.style.color = '#ffffff';
  btn.style.borderColor = '#10b981';
  setTimeout(() => {
    btn.innerHTML = originalHtml;
    btn.style.background = '';
    btn.style.color = '';
    btn.style.borderColor = '';
  }, 2200);
}

// Native Mobile Web Share API Handler
function shareStoryNative() {
  if (navigator.share) {
    navigator.share({
      title: <?php echo json_encode($article['title']); ?>,
      text: <?php echo json_encode($og_desc); ?>,
      url: window.location.href
    }).catch(() => {});
  }
}

// Enable native share button if supported (mobile browsers)
document.addEventListener('DOMContentLoaded', () => {
  if (navigator.share) {
    const nb = document.getElementById('nativeShareBtn');
    if (nb) nb.style.display = 'inline-flex';
  }
});

// Live Reading Progress Bar Engine
window.addEventListener('scroll', () => {
  const bar = document.getElementById('readingProgressBar');
  const btt = document.getElementById('backToTopBtn');
  if (bar) {
    const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = totalHeight > 0 ? (window.scrollY / totalHeight) * 100 : 0;
    bar.style.width = Math.min(100, Math.max(0, progress)) + '%';
  }
  if (btt) {
    if (window.scrollY > 400) {
      btt.classList.add('visible');
    } else {
      btt.classList.remove('visible');
    }
  }
});

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>