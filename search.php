<?php
require_once __DIR__ . '/includes/config.php';
$db = get_db();

$is_search_page = true;
$query = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['category_id'] ?? 0);

// Helper function to highlight matching search keywords in text safely
function highlight_search_term($text, $query) {
    if (empty($query)) return sanitize($text);
    $safe_text = sanitize($text);
    $safe_query = preg_quote(sanitize($query), '/');
    return preg_replace('/(' . $safe_query . ')/i', '<mark class="search-highlight">$1</mark>', $safe_text);
}

// Fetch Popular Tags for Quick Search Pills
$tags_stmt = $db->query("SELECT * FROM tags ORDER BY id ASC LIMIT 8");
$popular_tags = $tags_stmt ? $tags_stmt->fetchAll() : [];

// Fetch Search Results
$news_list = [];
if (!empty($query)) {
    $search_param = '%' . $query . '%';
    
    $sql = "
        SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
               a.full_name AS author_name,
               (SELECT COUNT(*) FROM comments WHERE news_id = n.id AND status = 'approved') AS comment_count
        FROM news n 
        JOIN categories c ON n.category_id = c.id 
        LEFT JOIN admins a ON n.author_id = a.id
        WHERE (n.title LIKE ? OR n.content LIKE ? OR n.excerpt LIKE ? OR n.sub_title LIKE ?) 
          AND n.status = 'published'
    ";
    
    $params = [$search_param, $search_param, $search_param, $search_param];
    
    if ($cat_filter > 0) {
        $sql .= " AND n.category_id = ?";
        $params[] = $cat_filter;
    }
    
    $sql .= " ORDER BY n.published_at DESC LIMIT 30";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $news_list = $stmt->fetchAll();
}

$page_title = !empty($query) ? "Search Results for \"" . sanitize($query) . "\"" : "Search News Archive";
require_once __DIR__ . '/includes/header.php';
?>

<style>
  /* ===== SEARCH RESULTS PAGE HIGH-END NEWSPAPER UI ===== */
  .search-hero-banner {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2.25rem 2rem;
    margin-bottom: 2rem;
    border-left: 6px solid var(--red);
    box-shadow: var(--shadow-s);
  }

  .search-tag-label {
    font-family: var(--font-head);
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    color: var(--red);
    letter-spacing: 0.5px;
    margin-bottom: 0.4rem;
    display: inline-block;
  }

  .search-hero-title {
    font-family: var(--font-head);
    font-size: 2.2rem;
    font-weight: 900;
    line-height: 1.15;
    color: var(--text);
    margin-bottom: 1.25rem;
    letter-spacing: -0.5px;
  }

  .search-input-form {
    display: flex;
    gap: 0.5rem;
    max-width: 760px;
    margin-bottom: 1.25rem;
  }
  .search-input-wrap {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
  }
  .search-input-icon {
    position: absolute;
    left: 1.1rem;
    color: var(--muted);
    font-size: 1rem;
    pointer-events: none;
  }
  .search-main-input {
    width: 100%;
    font-family: var(--font-body);
    font-size: 0.95rem;
    padding: 0.8rem 1.1rem 0.8rem 2.8rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg);
    color: var(--text);
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .search-main-input:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
  }
  .search-submit-btn {
    background: var(--red);
    color: #ffffff;
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 0.88rem;
    padding: 0.8rem 1.6rem;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: background 0.15s ease, transform 0.15s ease;
    white-space: nowrap;
  }
  .search-submit-btn:hover {
    background: var(--red-dk);
    transform: translateY(-1px);
  }

  .search-meta-info {
    font-size: 0.9rem;
    color: var(--muted);
  }
  .search-meta-query {
    font-family: var(--font-head);
    font-weight: 900;
    color: var(--text);
    font-size: 1.25rem;
  }
  .search-match-count {
    color: var(--red);
    font-weight: 800;
  }

  /* Trending Hashtags Bar */
  .search-topics-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
  }
  .topic-tag-chip {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--text);
    background: var(--bg);
    border: 1px solid var(--border);
    padding: 0.25rem 0.65rem;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .topic-tag-chip:hover {
    border-color: var(--red);
    color: var(--red);
  }

  /* Desk Filter Pills */
  .search-desk-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
  }
  .search-pill {
    padding: 0.4rem 0.95rem;
    border-radius: 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text);
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .search-pill:hover, .search-pill.active-pill {
    background: var(--red);
    color: #ffffff;
    border-color: var(--red);
  }

  /* Search Highlight Tag */
  mark.search-highlight {
    background: rgba(239, 68, 68, 0.22);
    color: var(--red);
    font-weight: 800;
    padding: 0 4px;
    border-radius: 3px;
  }

  /* Cards Grid */
  .search-card-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 3.5rem;
  }
  @media (min-width: 580px) { .search-card-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (min-width: 980px) { .search-card-grid { grid-template-columns: repeat(3, 1fr); } }

  .search-news-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-s);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  }
  .search-news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.14);
    border-color: rgba(239, 68, 68, 0.4);
  }

  .search-thumb-wrap {
    position: relative;
    width: 100%;
    height: 190px;
    overflow: hidden;
    background: #0f172a;
  }
  .search-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
  }
  .search-news-card:hover .search-thumb-img { transform: scale(1.06); }

  .search-card-body {
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.55rem;
  }

  .search-card-title {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 1.05rem;
    line-height: 1.35;
    color: var(--text);
  }
  .search-card-title a {
    color: var(--text);
    text-decoration: none;
    transition: color 0.15s ease;
  }
  .search-news-card:hover .search-card-title a { color: var(--red); }

  .search-card-excerpt {
    font-size: 0.83rem;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: auto;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .search-card-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.74rem;
    color: var(--dim);
    border-top: 1px solid var(--border);
    padding-top: 0.7rem;
    margin-top: 0.5rem;
  }

  /* Facebook Style Thumbs Up Like Button */
  .search-like-btn {
    background: rgba(24, 119, 242, 0.1);
    color: #1877f2;
    border: 1px solid rgba(24, 119, 242, 0.3);
    font-size: 0.76rem;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease;
    outline: none;
  }
  .search-like-btn:hover, .search-like-btn.liked {
    background: #1877f2 !important;
    color: #ffffff !important;
    border-color: #1877f2 !important;
    transform: scale(1.05);
  }
  .search-like-btn svg {
    flex-shrink: 0;
    width: 14px;
    height: 14px;
    fill: currentColor;
  }

  /* Responsive Rules */
  @media (max-width: 768px) {
    .search-hero-banner { padding: 1.25rem 1rem; border-radius: var(--radius); margin-bottom: 1.25rem; }
    .search-hero-title { font-size: 1.5rem; line-height: 1.2; letter-spacing: -0.3px; margin-bottom: 0.85rem; }
    .search-input-form { flex-direction: row; gap: 0.35rem; }
    .search-main-input { font-size: 0.85rem; padding: 0.65rem 0.85rem 0.65rem 2.4rem; }
    .search-input-icon { left: 0.8rem; font-size: 0.85rem; }
    .search-submit-btn { font-size: 0.8rem; padding: 0.65rem 1rem; }
    .search-meta-query { font-size: 1.05rem; }
    .search-card-grid { grid-template-columns: 1fr; gap: 1rem; margin-bottom: 2rem; }
    .search-news-card { border-radius: var(--radius); }
    .search-thumb-wrap { height: 185px; }
    .search-card-body { padding: 1rem; }
    .search-card-title { font-size: 1rem; }
    .search-filter-pills-bar { gap: 0.35rem; padding-bottom: 0.35rem; }
    .search-pill { font-size: 0.74rem; padding: 0.35rem 0.75rem; }
  }
</style>

<!-- Breadcrumb Navigation -->
<div class="article-breadcrumb" style="margin-bottom:1rem">
  <a href="index.php">Home</a> / 
  <span style="color:var(--muted)">Search News Archive</span>
</div>

<!-- Interactive Search Header Banner -->
<header class="search-hero-banner">
  <span class="search-tag-label">NEWSROOM ARCHIVE SEARCH</span>
  <h1 class="search-hero-title">Search Global Headlines & Reports</h1>

  <form action="search.php" method="GET" class="search-input-form">
    <div class="search-input-wrap">
      <span class="search-input-icon">🔍</span>
      <input type="text" name="q" value="<?php echo sanitize($query); ?>" class="search-main-input" placeholder="Search news headlines, topics, keywords..." required autofocus>
    </div>
    <button type="submit" class="search-submit-btn">Search News</button>
  </form>

  <?php if (!empty($query)): ?>
    <div class="search-meta-info">
      Results for <span class="search-meta-query">"<?php echo sanitize($query); ?>"</span> — 
      <span class="search-match-count"><?php echo count($news_list); ?> matching articles found</span>
    </div>
  <?php else: ?>
    <div class="search-meta-info">
      Enter keywords, topics, or article titles into the search bar above to explore our breaking news archive.
    </div>
  <?php endif; ?>

  <!-- Trending Topic Hashtags -->
  <?php if (!empty($popular_tags)): ?>
    <div class="search-topics-bar">
      <span style="font-family:var(--font-head);font-weight:900;font-size:0.75rem;color:var(--red);text-transform:uppercase">
        Trending Topics:
      </span>
      <?php foreach ($popular_tags as $tag): ?>
        <a href="search.php?q=<?php echo urlencode($tag['name']); ?>" class="topic-tag-chip">
          #<?php echo sanitize($tag['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</header>

<!-- Desk Filter Pills Bar -->
<?php if (!empty($query) && !empty($all_categories)): ?>
  <div class="search-desk-pills">
    <span style="font-family:var(--font-head);font-weight:800;font-size:0.75rem;color:var(--muted);text-transform:uppercase;margin-right:0.3rem">
      Filter Desk:
    </span>
    <a href="search.php?q=<?php echo urlencode($query); ?>" class="search-pill <?php echo ($cat_filter === 0) ? 'active-pill' : ''; ?>">
      All Desks
    </a>
    <?php foreach ($all_categories as $scat): ?>
      <a href="search.php?q=<?php echo urlencode($query); ?>&category_id=<?php echo $scat['id']; ?>" 
         class="search-pill <?php echo ($cat_filter === (int)$scat['id']) ? 'active-pill' : ''; ?>">
        <?php echo sanitize($scat['name']); ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Search Query Results Grid -->
<?php if (empty($query)): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:3rem;text-align:center;color:var(--muted)">
    <h3>Ready to Search</h3>
    <p style="font-size:.85rem;margin-top:6px">Type any headline, topic, or keyword into the search bar above to begin.</p>
  </div>
<?php elseif (empty($news_list)): ?>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:3rem;text-align:center;color:var(--muted)">
    <h3>No matching stories found for "<?php echo sanitize($query); ?>".</h3>
    <p style="font-size:.85rem;margin-top:6px">Try searching for broader keywords like <strong>"Technology"</strong>, <strong>"Economy"</strong>, <strong>"Climate"</strong>, or <strong>"Sports"</strong>.</p>
  </div>
<?php else: ?>
  <div class="search-card-grid">
    <?php foreach ($news_list as $item): ?>
      <?php $is_item_liked = !empty($_SESSION['liked_articles'][$item['id']]); ?>
      <article class="search-news-card">
        
        <div class="search-thumb-wrap">
          <?php $item_img = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=600&q=80'; ?>
          <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>">
            <img src="<?php echo sanitize($item_img); ?>" alt="<?php echo sanitize($item['title']); ?>" class="search-thumb-img">
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

        <div class="search-card-body">
          <h3 class="search-card-title">
            <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>">
              <?php echo highlight_search_term($item['title'], $query); ?>
            </a>
          </h3>
          
          <p class="search-card-excerpt">
            <?php echo highlight_search_term($item['excerpt'] ?: short_text(strip_tags($item['content']), 110), $query); ?>
          </p>

          <div class="search-card-foot">
            <span>⏱ <?php echo $item['read_time_minutes']; ?>m read • <?php echo time_ago($item['published_at']); ?></span>
            
            <div style="display:flex;align-items:center;gap:.6rem">
              <span>👁 <?php echo number_format($item['views']); ?></span>
              
              <button type="button" class="search-like-btn <?php echo $is_item_liked ? 'liked' : ''; ?>" onclick="likeSearchArticle(<?php echo $item['id']; ?>, this)">
                <svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3v11z"/></svg> 
                <span class="like-cnt"><?php echo number_format($item['likes']); ?></span>
              </button>
            </div>
          </div>
        </div>

      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Facebook Style Like Button Engine for Search Cards -->
<script>
function likeSearchArticle(newsId, btn) {
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
