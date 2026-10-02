<?php
require_once __DIR__ . '/includes/config.php';
$db = get_db();

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: index.php');
    exit;
}

// Fetch Category Details
$cat_stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
$cat_stmt->execute([$slug]);
$category = $cat_stmt->fetch();

if (!$category) {
    header('Location: index.php');
    exit;
}

$page_title = $category['name'] . " News Archive & In-Depth Reporting";
$meta_desc = $category['description'] ?: "Read the latest " . $category['name'] . " news stories, breaking updates, and coverage on CPL NEWS.";
require_once __DIR__ . '/includes/header.php';

// Fetch Sister Categories in the Same Navbar Group
$sister_stmt = $db->prepare("SELECT * FROM categories WHERE group_name = ? AND is_active = 1 ORDER BY display_order ASC");
$sister_stmt->execute([$category['group_name']]);
$sister_categories = $sister_stmt->fetchAll();

// Pagination Config
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

// Count Total Articles in Category
$count_stmt = $db->prepare("SELECT COUNT(*) FROM news WHERE category_id = ? AND status = 'published'");
$count_stmt->execute([$category['id']]);
$total_articles = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_articles / $limit));

// Fetch Paginated Articles for Category
$stmt = $db->prepare("
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
           a.full_name AS author_name,
           (SELECT COUNT(*) FROM comments WHERE news_id = n.id AND status = 'approved') AS comment_count
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    LEFT JOIN admins a ON n.author_id = a.id
    WHERE c.id = ? AND n.status = 'published'
    ORDER BY n.published_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $category['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$news_list = $stmt->fetchAll();

// First article on Page 1 serves as Category Spotlight Story
$spotlight_story = ($page === 1 && !empty($news_list)) ? array_shift($news_list) : null;
?>

<style>
  /* ===== CATEGORY ARCHIVE PAGE DESIGN ===== */
  .cat-hero-banner {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 6px solid <?php echo sanitize($category['color_code'] ?: '#ef4444'); ?>;
    box-shadow: var(--shadow-s);
  }
  
  .cat-group-tag {
    font-family: var(--font-head);
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    color: <?php echo sanitize($category['color_code'] ?: '#ef4444'); ?>;
    letter-spacing: 0.5px;
    margin-bottom: 0.4rem;
    display: inline-block;
  }

  .cat-hero-title {
    font-family: var(--font-head);
    font-size: 2.2rem;
    font-weight: 900;
    line-height: 1.15;
    color: var(--text);
    margin-bottom: 0.65rem;
    letter-spacing: -0.5px;
  }
  @media (max-width: 640px) {
    .cat-hero-title { font-size: 1.6rem; }
  }

  .cat-hero-desc {
    font-size: 0.92rem;
    color: var(--muted);
    line-height: 1.6;
    max-width: 800px;
    margin-bottom: 1rem;
  }

  .cat-meta-pills {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.78rem;
    color: var(--dim);
    flex-wrap: wrap;
  }
  .cat-count-badge {
    background: var(--bg);
    border: 1px solid var(--border);
    padding: 4px 12px;
    border-radius: 15px;
    font-weight: 700;
  }

  /* Sister Desk Navigation Bar */
  .sister-desk-bar {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--border);
  }
  .sister-pill {
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text);
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.15s ease;
  }
  .sister-pill:hover {
    border-color: var(--red);
    color: var(--red);
  }
  .sister-pill.active-cat {
    background: <?php echo sanitize($category['color_code'] ?: '#ef4444'); ?>;
    color: #ffffff;
    border-color: <?php echo sanitize($category['color_code'] ?: '#ef4444'); ?>;
  }

  /* Category Spotlight Lead Feature Card */
  .cat-spotlight-card {
    background: #090d16;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr;
    margin-bottom: 2.25rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
    text-decoration: none;
    color: #ffffff;
    transition: transform 0.25s ease;
  }
  @media (min-width: 860px) {
    .cat-spotlight-card { grid-template-columns: 1.4fr 1fr; }
  }
  .cat-spotlight-card:hover { transform: translateY(-4px); }

  .cat-spotlight-thumb {
    width: 100%;
    height: 360px;
    object-fit: cover;
  }
  @media (max-width: 860px) {
    .cat-spotlight-thumb { height: 240px; }
  }

  .cat-spotlight-body {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: linear-gradient(135deg, #090d16 0%, #0f172a 100%);
  }

  /* Article Cards Grid */
  .cat-card-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 3rem;
  }
  @media (min-width: 580px) { .cat-card-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (min-width: 980px) { .cat-card-grid { grid-template-columns: repeat(3, 1fr); } }

  .cat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-s);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  }
  .cat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.14);
    border-color: rgba(239, 68, 68, 0.4);
  }

  .cat-thumb-wrap {
    position: relative;
    width: 100%;
    height: 190px;
    overflow: hidden;
    background: #0f172a;
  }
  .cat-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
  }
  .cat-card:hover .cat-thumb-img { transform: scale(1.06); }

  .cat-card-body {
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.55rem;
  }
  .cat-card-title {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 1.05rem;
    line-height: 1.35;
    color: var(--text);
  }
  .cat-card-title a {
    color: var(--text);
    text-decoration: none;
    transition: color 0.15s ease;
  }
  .cat-card:hover .cat-card-title a { color: var(--red); }

  .cat-card-excerpt {
    font-size: 0.83rem;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: auto;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .cat-card-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.74rem;
    color: var(--dim);
    border-top: 1px solid var(--border);
    padding-top: 0.7rem;
    margin-top: 0.5rem;
  }

  .latest-like-btn {
    background: rgba(24, 119, 242, 0.1);
    color: #1877f2;
    border: 1px solid rgba(24, 119, 242, 0.3);
    font-size: 0.74rem;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease;
  }
  .latest-like-btn:hover, .latest-like-btn.liked {
    background: #1877f2;
    color: #ffffff;
    border-color: #1877f2;
    transform: scale(1.05);
  }

  /* Pagination Controls Bar */
  .pagination-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 3.5rem;
    flex-wrap: wrap;
  }
  .page-link-btn {
    padding: 0.55rem 0.95rem;
    border-radius: var(--radius);
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 0.82rem;
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .page-link-btn:hover {
    border-color: var(--red);
    color: var(--red);
  }
  .page-link-btn.active-page {
    background: var(--red);
    color: #ffffff;
    border-color: var(--red);
  }
  .page-link-btn.disabled {
    opacity: 0.4;
    pointer-events: none;
  }

  .sister-desk-bar::-webkit-scrollbar { display: none; }

  /* ===== MOBILE RESPONSIVE LAYOUT ENHANCEMENTS ===== */
  @media (max-width: 768px) {
    .cat-hero-banner { padding: 1.25rem 1rem; border-radius: var(--radius); margin-bottom: 1.25rem; }
    .cat-hero-title { font-size: 1.5rem; line-height: 1.2; letter-spacing: -0.3px; }
    .cat-hero-desc { font-size: 0.85rem; line-height: 1.5; }
    .sister-desk-bar { gap: 0.35rem; margin-bottom: 1.25rem; padding-bottom: 0.35rem; }
    .sister-pill { font-size: 0.74rem; padding: 0.35rem 0.75rem; }
    .cat-spotlight-card { border-radius: var(--radius); margin-bottom: 1.5rem; }
    .cat-spotlight-thumb { height: 210px; }
    .cat-spotlight-body { padding: 1.1rem; }
    .cat-card-grid { grid-template-columns: 1fr; gap: 1rem; margin-bottom: 2rem; }
    .cat-card { border-radius: var(--radius); }
    .cat-thumb-wrap { height: 185px; }
    .cat-card-body { padding: 1rem; }
    .cat-card-title { font-size: 1rem; }
    .pagination-bar { gap: 0.3rem; margin-bottom: 2.25rem; }
    .page-link-btn { padding: 0.45rem 0.75rem; font-size: 0.76rem; border-radius: var(--radius-s); }
  }
</style>

<!-- Breadcrumb Navigation -->
<div class="article-breadcrumb" style="margin-bottom:1rem">
  <a href="index.php">Home</a> / 
  <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>">Desks</a> / 
  <span style="color:var(--muted)"><?php echo sanitize($category['name']); ?></span>
</div>

<!-- Category Hero Header Banner -->
<header class="cat-hero-banner">
  <span class="cat-group-tag"><?php echo sanitize($category['group_name']); ?> DESK</span>
  <h1 class="cat-hero-title"><?php echo sanitize($category['name']); ?></h1>
  
  <?php if (!empty($category['description'])): ?>
    <p class="cat-hero-desc"><?php echo sanitize($category['description']); ?></p>
  <?php endif; ?>

  <div class="cat-meta-pills">
    <span class="cat-count-badge">📊 <?php echo number_format($total_articles); ?> Articles Published</span>
    <span class="cat-count-badge">📄 Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
  </div>
</header>

<!-- Sister Desk Navigation Tabs -->
<?php if (count($sister_categories) > 1): ?>
  <nav class="sister-desk-bar" aria-label="Sister News Desks">
    <span style="font-family:var(--font-head);font-weight:800;font-size:0.75rem;color:var(--muted);text-transform:uppercase;margin-right:0.3rem">
      Desk Hub:
    </span>
    <?php foreach ($sister_categories as $scat): ?>
      <a href="category.php?slug=<?php echo urlencode($scat['slug']); ?>" 
         class="sister-pill <?php echo ($scat['slug'] === $category['slug']) ? 'active-cat' : ''; ?>">
        <?php echo sanitize($scat['name']); ?>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<!-- Category Spotlight Lead Feature Story (Page 1 Only) -->
<?php if ($spotlight_story): ?>
  <a href="article.php?id=<?php echo $spotlight_story['id']; ?>&slug=<?php echo urlencode($spotlight_story['slug']); ?>" class="cat-spotlight-card">
    <?php $sp_img = !empty($spotlight_story['image_url']) ? $spotlight_story['image_url'] : 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=900&q=80'; ?>
    <img src="<?php echo sanitize($sp_img); ?>" alt="<?php echo sanitize($spotlight_story['title']); ?>" class="cat-spotlight-thumb">
    
    <div class="cat-spotlight-body">
      <div style="display:flex;gap:0.4rem;align-items:center;margin-bottom:0.5rem">
        <span class="cat-badge" style="background:<?php echo sanitize($spotlight_story['color_code'] ?: '#ef4444'); ?>">
          SPOTLIGHT STORY
        </span>
        <?php if (!empty($spotlight_story['is_breaking'])): ?>
          <span class="cat-badge" style="background:var(--red)"><span class="live-dot"></span> BREAKING</span>
        <?php endif; ?>
      </div>

      <h2 style="font-family:var(--font-head);font-size:1.6rem;font-weight:900;line-height:1.25;margin-bottom:0.75rem">
        <?php echo sanitize($spotlight_story['title']); ?>
      </h2>

      <p style="font-size:0.86rem;color:#cbd5e1;line-height:1.6;margin-bottom:1.25rem">
        <?php echo sanitize($spotlight_story['excerpt'] ?: short_text(strip_tags($spotlight_story['content']), 140)); ?>
      </p>

      <div style="display:flex;align-items:center;gap:1rem;font-size:0.78rem;color:#94a3b8">
        <span>By <b><?php echo sanitize($spotlight_story['author_name'] ?: 'CPL Newsroom'); ?></b></span>
        <span>⏱ <?php echo $spotlight_story['read_time_minutes']; ?> min read</span>
        <span>📅 <?php echo time_ago($spotlight_story['published_at']); ?></span>
      </div>
    </div>
  </a>
<?php endif; ?>

<!-- Category Articles Grid -->
<?php if (empty($news_list) && !$spotlight_story): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:3rem;text-align:center;color:var(--muted)">
    <h3>No articles published in <?php echo sanitize($category['name']); ?> yet.</h3>
    <p style="font-size:.85rem;margin-top:6px">Check back soon for latest breaking reports in this news desk!</p>
  </div>
<?php else: ?>
  <div class="cat-card-grid">
    <?php foreach ($news_list as $item): ?>
      <?php $is_item_liked = !empty($_SESSION['liked_articles'][$item['id']]); ?>
      <article class="cat-card">
        
        <div class="cat-thumb-wrap">
          <?php $item_img = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=600&q=80'; ?>
          <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>">
            <img src="<?php echo sanitize($item_img); ?>" alt="<?php echo sanitize($item['title']); ?>" class="cat-thumb-img">
          </a>
          
          <span class="cat-badge" style="position:absolute;top:10px;left:10px;background:<?php echo sanitize($item['color_code'] ?: '#ef4444'); ?>">
            <?php echo sanitize($item['category_name']); ?>
          </span>

          <?php if (!empty($item['video_url'])): ?>
            <span class="cat-badge" style="position:absolute;top:10px;right:10px;background:var(--blue)">
              Video
            </span>
          <?php endif; ?>
        </div>

        <div class="cat-card-body">
          <h3 class="cat-card-title">
            <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>">
              <?php echo sanitize($item['title']); ?>
            </a>
          </h3>
          
          <p class="cat-card-excerpt">
            <?php echo sanitize($item['excerpt'] ?: short_text(strip_tags($item['content']), 110)); ?>
          </p>

          <div class="cat-card-foot">
            <span>⏱ <?php echo $item['read_time_minutes']; ?>m read • <?php echo time_ago($item['published_at']); ?></span>
            
            <div style="display:flex;align-items:center;gap:.6rem">
              <span>👁 <?php echo number_format($item['views']); ?></span>
              
              <button type="button" class="latest-like-btn <?php echo $is_item_liked ? 'liked' : ''; ?>" onclick="likeCatArticle(<?php echo $item['id']; ?>, this)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3v11z"/></svg> 
                <span class="like-cnt"><?php echo number_format($item['likes']); ?></span>
              </button>
            </div>
          </div>
        </div>

      </article>
    <?php endforeach; ?>
  </div>

  <!-- Pagination Controls Bar -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination-bar">
      <!-- Previous Page -->
      <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>&page=<?php echo $page - 1; ?>" 
         class="page-link-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
        ‹ Prev
      </a>

      <!-- Numeric Page Buttons -->
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>&page=<?php echo $p; ?>" 
           class="page-link-btn <?php echo ($p === $page) ? 'active-page' : ''; ?>">
          <?php echo $p; ?>
        </a>
      <?php endfor; ?>

      <!-- Next Page -->
      <a href="category.php?slug=<?php echo urlencode($category['slug']); ?>&page=<?php echo $page + 1; ?>" 
         class="page-link-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
        Next ›
      </a>
    </div>
  <?php endif; ?>

<?php endif; ?>

<!-- Like Button Engine for Category Cards -->
<script>
function likeCatArticle(newsId, btn) {
  const cnt = btn.querySelector('.like-cnt');
  const isLikedNow = btn.classList.contains('liked');
  let current = parseInt((cnt ? cnt.textContent : '0').replace(/,/g, '')) || 0;
  
  // 1. INSTANT 0ms Optimistic UI Toggle Update
  let optimisticLikes = (isLikedNow ? Math.max(0, current - 1) : current + 1).toLocaleString();
  if (cnt) cnt.textContent = optimisticLikes;
  
  if (isLikedNow) {
    btn.classList.remove('liked');
  } else {
    btn.classList.add('liked');
  }

  // 2. Async background sync with server session
  fetch('api.php?action=like_article&news_id=' + newsId, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const serverLikes = d.new_likes || (d.likes !== undefined ? d.likes.toLocaleString() : null);
        if (cnt && serverLikes !== null) cnt.textContent = serverLikes;
        if (d.is_liked) {
          btn.classList.add('liked');
        } else {
          btn.classList.remove('liked');
        }
      }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
