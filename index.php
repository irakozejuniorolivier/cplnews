<?php
$page_title = "Actualités Internationales & Grands Reportages";
require_once __DIR__ . '/includes/header.php';

// 1. Fetch Featured Stories Playlist (for Hero Slider & Interactive Right Sidelist)
$featured_stmt = $db->query("
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
           a.full_name AS author_name, a.avatar_url AS author_avatar
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    LEFT JOIN admins a ON n.author_id = a.id
    WHERE n.is_featured = 1 AND n.status = 'published'
    ORDER BY n.published_at DESC 
    LIMIT 5
");
$featured_news = $featured_stmt ? $featured_stmt->fetchAll() : [];

if (empty($featured_news)) {
    $featured_stmt = $db->query("
        SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
               a.full_name AS author_name, a.avatar_url AS author_avatar
        FROM news n 
        JOIN categories c ON n.category_id = c.id 
        LEFT JOIN admins a ON n.author_id = a.id
        WHERE n.status = 'published'
        ORDER BY n.published_at DESC 
        LIMIT 5
    ");
    $featured_news = $featured_stmt ? $featured_stmt->fetchAll() : [];
}

// 2. Fetch Trending Tags / Topic Hashtags
$tags_stmt = $db->query("SELECT * FROM tags ORDER BY id ASC LIMIT 8");
$popular_tags = $tags_stmt ? $tags_stmt->fetchAll() : [];

// 2b. Fetch Categories for Native Mobile App Quick Ribbon
$ribbon_cats_stmt = $db->query("SELECT id, name, slug FROM categories ORDER BY id ASC LIMIT 10");
$ribbon_categories = $ribbon_cats_stmt ? $ribbon_cats_stmt->fetchAll() : [];

// 3. Fetch Editors' Pick Stories (Strictly is_editors_pick = 1 AND status = 'published')
$editors_stmt = $db->query("
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
           a.full_name AS author_name, a.avatar_url AS author_avatar
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    LEFT JOIN admins a ON n.author_id = a.id
    WHERE n.is_editors_pick = 1 AND n.status = 'published'
    ORDER BY n.published_at DESC 
    LIMIT 4
");
$editors_picks = $editors_stmt ? $editors_stmt->fetchAll() : [];

// 4. Fetch Video & Multimedia Desk Stories
$video_stmt = $db->query("
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code 
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    WHERE n.video_url IS NOT NULL AND n.video_url != '' AND n.status = 'published'
    ORDER BY n.published_at DESC 
    LIMIT 3
");
$video_stories = $video_stmt ? $video_stmt->fetchAll() : [];

// 5. Fetch Opinion & Columnist Commentary Stories
$opinion_stmt = $db->query("
    SELECT n.*, c.name AS category_name, c.color_code,
           a.full_name AS author_name, a.avatar_url AS author_avatar
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    LEFT JOIN admins a ON n.author_id = a.id
    WHERE (c.slug = 'opinion' OR c.slug = 'lifestyle') AND n.status = 'published'
    ORDER BY n.published_at DESC 
    LIMIT 3
");
$opinion_news = $opinion_stmt ? $opinion_stmt->fetchAll() : [];

// 6. Fetch Latest Published Grid News (excluding featured stories)
$exclude_ids = !empty($featured_news) ? array_column($featured_news, 'id') : [0];
$in_clause = implode(',', array_fill(0, count($exclude_ids), '?'));

$grid_stmt = $db->prepare("
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color_code,
           a.full_name AS author_name,
           (SELECT COUNT(*) FROM comments WHERE news_id = n.id AND status = 'approved') AS comment_count
    FROM news n 
    JOIN categories c ON n.category_id = c.id 
    LEFT JOIN admins a ON n.author_id = a.id
    WHERE n.id NOT IN ($in_clause) AND n.status = 'published'
    ORDER BY n.published_at DESC 
    LIMIT 12
");
$grid_stmt->execute($exclude_ids);
$grid_news = $grid_stmt->fetchAll();
?>

<style>
  /* ===== 1. GLOBAL LAYOUT SAFEGUARDS ===== */
  .main-content {
    overflow-x: clip;
  }
  .hero-layout { 
    display: grid; 
    grid-template-columns: 1fr; 
    gap: 1.5rem; 
    margin-bottom: 2.25rem;
    min-width: 0;
  }
  @media (min-width: 960px) { 
    .hero-layout { grid-template-columns: 2fr 1.05fr; gap: 1.75rem; } 
  }

  /* ===== 2. NATIVE MOBILE APP CATEGORY QUICK-RIBBON ===== */
  .mobile-app-category-ribbon {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding: 0.25rem 0.1rem 0.75rem 0.1rem;
    margin-bottom: 0.85rem;
  }
  .mobile-app-category-ribbon::-webkit-scrollbar { display: none; }

  .app-cat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 6px 14px;
    border-radius: 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: var(--font-display);
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .app-cat-pill:hover, .app-cat-pill.active {
    background: var(--red);
    color: #ffffff;
    border-color: var(--red);
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.35);
    transform: translateY(-1px);
  }
  .app-cat-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ffffff;
    display: inline-block;
  }

  /* ===== 3. HERO SPOTLIGHT SHOWCASE ===== */
  .hero-slider-box {
    position: relative;
    background: #090d16;
    border-radius: var(--radius-lg);
    overflow: hidden;
    min-height: 520px;
    height: 520px;
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.4);
    border: 1px solid var(--border);
    display: flex;
    user-select: none;
    touch-action: pan-y;
  }
  @media (max-width: 959px) {
    .hero-slider-box {
      min-height: 380px;
      height: clamp(360px, 54vh, 440px);
      border-radius: 16px;
    }
  }

  /* Story Segment Bars across top */
  .hero-story-segments {
    position: absolute;
    top: 12px;
    left: 14px;
    right: 14px;
    z-index: 12;
    display: flex;
    gap: 5px;
    pointer-events: none;
  }
  .story-segment-bar {
    flex: 1;
    height: 3px;
    background: rgba(255, 255, 255, 0.28);
    border-radius: 2px;
    overflow: hidden;
  }
  .story-segment-fill {
    width: 0%;
    height: 100%;
    background: #ffffff;
    border-radius: 2px;
    transition: width 0.3s ease;
  }
  .story-segment-bar.active .story-segment-fill {
    width: 100%;
    background: var(--red);
    box-shadow: 0 0 6px var(--red);
  }
  .story-segment-bar.passed .story-segment-fill {
    width: 100%;
    background: rgba(255, 255, 255, 0.85);
  }

  .hero-slide-item {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1), transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    transform: scale(1.02);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    text-decoration: none;
  }
  .hero-slide-item.active {
    opacity: 1;
    visibility: visible;
    transform: scale(1);
    z-index: 2;
  }

  .hero-media-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.72;
    transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease;
  }
  .hero-slide-item:hover .hero-media-img {
    transform: scale(1.04);
    opacity: 0.85;
  }

  .hero-slide-overlay {
    position: relative;
    z-index: 3;
    padding: 3rem 2.25rem 2rem 2.25rem;
    background: linear-gradient(180deg, rgba(9, 13, 22, 0.35) 0%, rgba(9, 13, 22, 0.05) 25%, rgba(9, 13, 22, 0.72) 60%, rgba(9, 13, 22, 0.98) 100%);
    color: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }
  @media (max-width: 768px) {
    .hero-slide-overlay {
      padding: 1.75rem 1.15rem 1.25rem 1.15rem;
      gap: 0.5rem;
    }
  }

  .hero-badges-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  .cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 4px;
    color: #fff;
    letter-spacing: 0.6px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
  }

  .breaking-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--red);
    color: #ffffff;
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 900;
    padding: 4px 10px;
    border-radius: 4px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.4);
  }

  .hero-kicker {
    color: #fb7185;
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .lead-title {
    font-family: var(--font-serif);
    font-size: 2.35rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.02em;
    color: #ffffff;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.7);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  @media (max-width: 960px) { .lead-title { font-size: 1.75rem; } }
  @media (max-width: 640px) { .lead-title { font-size: 1.35rem; line-height: 1.22; -webkit-line-clamp: 3; } }

  .lead-meta {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    font-family: var(--font-display);
    font-size: 0.76rem;
    color: #cbd5e1;
    flex-wrap: wrap;
    margin-top: 0.2rem;
  }
  .lead-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }
  .lead-meta-item svg {
    color: var(--red);
    flex-shrink: 0;
  }
  .hero-author-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    border: 1.5px solid #ffffff;
    flex-shrink: 0;
  }

  /* Frosted Glass Floating Nav Arrows (Desktop Only) */
  .hero-arrow-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: rgba(11, 17, 32, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    font-size: 1.25rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(8px);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
  }
  .hero-arrow-btn:hover {
    background: var(--red);
    border-color: var(--red);
    transform: translateY(-50%) scale(1.08);
  }
  .hero-arrow-prev { left: 16px; }
  .hero-arrow-next { right: 16px; }
  @media (max-width: 959px) { .hero-arrow-btn { display: none; } }

  /* Mobile Swipe Hint Capsule */
  .mobile-swipe-badge {
    display: none;
    position: absolute;
    bottom: 12px;
    right: 14px;
    z-index: 10;
    background: rgba(11, 17, 32, 0.75);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #ffffff;
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 12px;
    letter-spacing: 0.5px;
    pointer-events: none;
  }
  @media (max-width: 959px) {
    .mobile-swipe-badge { display: inline-flex; align-items: center; gap: 4px; }
  }

  /* ===== 4. EDITORIAL WIRE PLAYLIST (DESKTOP) ===== */
  .sidelist-column-wrap {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }
  @media (max-width: 959px) {
    .sidelist-column-wrap {
      display: none !important;
    }
  }
  .featured-sidelist-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    height: 520px;
    overflow-y: auto;
  }
  .sidelist-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0.75rem;
    margin-bottom: 0.35rem;
  }
  .sidelist-head-title {
    font-family: var(--font-display);
    font-size: 0.85rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }
  .sidelist-head-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--red);
  }
  .sidelist-counter-badge {
    font-family: var(--font-display);
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--red);
    background: var(--red-bg);
    padding: 2px 8px;
    border-radius: 12px;
  }

  .sidelist-item {
    display: flex;
    gap: 0.85rem;
    align-items: center;
    padding: 0.7rem 0.85rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    border-left: 4px solid transparent;
    background: var(--surface);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    text-decoration: none;
  }
  .sidelist-item:hover {
    background: var(--surface-hover);
    border-color: rgba(225, 29, 72, 0.3);
    transform: translateX(3px);
  }
  .sidelist-item:active {
    transform: scale(0.98);
  }
  .sidelist-item.active-sidelist {
    background: var(--red-bg);
    border-left-color: var(--red);
    border-color: rgba(225, 29, 72, 0.35);
    box-shadow: 0 4px 15px rgba(225, 29, 72, 0.08);
  }

  .sidelist-num {
    font-family: var(--font-display);
    font-size: 1.15rem;
    font-weight: 900;
    color: var(--dim);
    line-height: 1;
    flex-shrink: 0;
    width: 24px;
    transition: color 0.2s ease;
  }
  .sidelist-item.active-sidelist .sidelist-num {
    color: var(--red);
  }

  .sidelist-thumb-wrap {
    width: 76px;
    height: 58px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: #0f172a;
    border: 1px solid var(--border);
  }
  .sidelist-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
  }
  .sidelist-item:hover .sidelist-thumb {
    transform: scale(1.08);
  }

  .sidelist-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
  }
  .sidelist-cat-pill {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .sidelist-item-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.85rem;
    line-height: 1.35;
    color: var(--text);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .sidelist-meta-row {
    font-size: 0.68rem;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  /* ===== 5. TRENDING HASHTAGS TOPIC BAR ===== */
  .trending-tags-bar {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0.75rem 1.25rem;
    margin-bottom: 2.5rem;
    box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
    flex-wrap: wrap;
  }
  @media (max-width: 768px) {
    .trending-tags-bar {
      flex-wrap: nowrap;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      padding: 0.6rem 0.85rem;
      gap: 0.6rem;
      margin-bottom: 2rem;
    }
    .trending-tags-bar::-webkit-scrollbar { display: none; }
  }
  .tags-bar-label {
    font-family: var(--font-display);
    font-size: 0.76rem;
    font-weight: 900;
    color: var(--red);
    letter-spacing: 0.6px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .tags-bar-list {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
  }
  @media (max-width: 768px) {
    .tags-bar-list {
      flex-wrap: nowrap;
      gap: 0.4rem;
    }
  }
  .tag-chip {
    font-family: var(--font-display);
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--text);
    background: var(--bg);
    border: 1px solid var(--border);
    padding: 0.25rem 0.7rem;
    border-radius: 15px;
    text-decoration: none;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    flex-shrink: 0;
  }
  .tag-chip:hover {
    border-color: var(--red);
    color: #ffffff;
    background: var(--red);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(225, 29, 72, 0.25);
  }

  /* ===== 6. EDITORS' PICKED FEATURES SECTION ===== */
  .editors-section { margin-bottom: 2.75rem; }
  .section-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 4px solid var(--red);
    padding-left: 0.85rem;
    margin-bottom: 1.35rem;
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  .section-header-title {
    font-family: var(--font-head);
    font-size: 1.25rem;
    font-weight: 900;
    text-transform: uppercase;
    color: var(--text);
    letter-spacing: -0.3px;
    margin: 0;
  }
  .section-header-sub {
    font-size: 0.8rem;
    color: var(--muted);
    font-weight: 600;
    display: block;
    margin-top: 2px;
  }

  .editors-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
  }
  @media (min-width: 580px) { .editors-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (min-width: 980px) { .editors-grid { grid-template-columns: repeat(4, 1fr); } }

  .editors-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-s);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease, border-color 0.25s ease;
    text-decoration: none;
  }
  .editors-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.14);
    border-color: rgba(225, 29, 72, 0.4);
  }

  .editors-thumb-wrap {
    position: relative;
    width: 100%;
    height: 175px;
    overflow: hidden;
    background: #0f172a;
  }
  .editors-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .editors-card:hover .editors-thumb-img {
    transform: scale(1.06);
  }

  .editors-card-body {
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.5rem;
  }
  .editors-card-title {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 1rem;
    line-height: 1.38;
    color: var(--text);
    transition: color 0.15s ease;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .editors-card:hover .editors-card-title { color: var(--red); }

  .editors-card-excerpt {
    font-size: 0.82rem;
    color: var(--muted);
    line-height: 1.48;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: auto;
  }

  .editors-card-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.74rem;
    color: var(--dim);
    border-top: 1px solid var(--border);
    padding-top: 0.65rem;
    margin-top: 0.5rem;
  }

  /* ===== 7. BROADCAST DESK VIDEO HUB ===== */
  @keyframes pulseLive {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
  }

  .broadcast-section {
    background: #090d16;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 2.75rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
  }

  .broadcast-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--red);
    padding-bottom: 0.85rem;
    margin-bottom: 1.35rem;
  }
  .broadcast-pill {
    background: var(--red);
    color: #ffffff;
    font-family: var(--font-head);
    font-weight: 900;
    font-size: 0.75rem;
    padding: 4px 12px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }
  .broadcast-title {
    font-family: var(--font-head);
    color: #ffffff;
    font-weight: 900;
    font-size: 1.15rem;
    text-transform: uppercase;
    letter-spacing: -0.2px;
    margin: 0;
  }

  .broadcast-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
  }
  @media (min-width: 768px) {
    .broadcast-grid { grid-template-columns: repeat(3, 1fr); }
  }

  .video-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: var(--radius);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    transition: transform 0.25s ease, border-color 0.25s ease;
  }
  .video-card:hover {
    transform: translateY(-4px);
    border-color: rgba(225, 29, 72, 0.5);
  }

  .video-thumb-box {
    position: relative;
    width: 100%;
    height: 180px;
    background: #000;
    cursor: pointer;
    overflow: hidden;
  }
  .video-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.8;
    transition: transform 0.4s ease, opacity 0.4s ease;
  }
  .video-card:hover .video-thumb-img {
    transform: scale(1.05);
    opacity: 0.9;
  }

  .video-play-btn {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 52px; height: 52px;
    border-radius: 50%;
    background: var(--red);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 8px 25px rgba(225, 29, 72, 0.6);
    transition: all 0.2s ease;
  }
  .video-thumb-box:hover .video-play-btn {
    transform: translate(-50%, -50%) scale(1.12);
    background: #dc2626;
  }

  .video-card-body {
    padding: 1.1rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.5rem;
  }
  .video-card-title {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 0.95rem;
    line-height: 1.35;
    color: #ffffff;
    margin: 0 0 auto 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .video-card-meta {
    font-size: 0.73rem;
    color: #9ca3af;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #1f2937;
    padding-top: 0.6rem;
    margin-top: 0.4rem;
  }

  .video-action-bar {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.65rem;
  }
  .v-btn-play {
    flex: 1;
    background: var(--red);
    color: #ffffff;
    border: none;
    border-radius: 6px;
    padding: 0.55rem 0.75rem;
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 0.78rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    transition: background 0.15s ease;
    text-decoration: none;
  }
  .v-btn-play:hover { background: #dc2626; }

  .v-btn-details {
    background: #1f2937;
    color: #e5e7eb;
    border: 1px solid #374151;
    border-radius: 6px;
    padding: 0.55rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .v-btn-details:hover { background: #374151; color: #ffffff; }

  /* In-Page Video Player Modal Overlay */
  .video-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(9, 13, 22, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 9999999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }
  .video-modal-overlay.active { display: flex !important; }

  .video-modal-container {
    width: 100%;
    max-width: 820px;
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: var(--radius-lg);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.7);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .video-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
  }
  .video-modal-headline {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 1.1rem;
    color: #ffffff;
    margin-top: 0.25rem;
    line-height: 1.3;
  }
  .video-modal-close {
    background: #1e293b;
    color: #ffffff;
    border: 1px solid #334155;
    width: 32px; height: 32px;
    border-radius: 50%;
    font-weight: 900;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s ease;
  }
  .video-modal-close:hover { background: var(--red); border-color: var(--red); }

  .video-modal-player-box {
    width: 100%;
    height: 440px;
    background: #000;
    border-radius: var(--radius);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  @media (max-width: 640px) {
    .video-modal-player-box { height: 250px; }
  }

  .video-modal-footer {
    display: flex;
    justify-content: flex-end;
  }
  .video-modal-detail-btn {
    background: var(--red);
    color: #ffffff;
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 0.85rem;
    padding: 0.6rem 1.2rem;
    border-radius: 20px;
    text-decoration: none;
    transition: background 0.15s ease;
  }
  .video-modal-detail-btn:hover { background: #dc2626; }

  /* ===== 8. OPINION & COLUMNISTS DESK ===== */
  .opinion-section { margin-bottom: 2.75rem; }
  .opinion-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
  }
  @media (min-width: 768px) { .opinion-grid { grid-template-columns: repeat(3, 1fr); } }

  .opinion-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    box-shadow: var(--shadow-s);
    text-decoration: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  }
  .opinion-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
    border-color: rgba(99, 102, 241, 0.4);
  }

  .opinion-author-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  .opinion-author-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2.5px solid var(--blue);
  }
  .opinion-author-name {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 0.9rem;
    color: var(--text);
  }
  .opinion-author-title {
    font-size: 0.74rem;
    color: var(--muted);
  }

  .opinion-quote-title {
    font-family: var(--font-serif);
    font-style: italic;
    font-weight: 700;
    font-size: 1.05rem;
    line-height: 1.38;
    color: var(--text);
    margin: 0;
  }

  /* ===== 9. LATEST PUBLISHED NEWS SECTION ===== */
  .latest-section { margin-bottom: 2.75rem; }
  .latest-cat-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
  }
  .cat-filter-pill {
    padding: 0.3rem 0.8rem;
    border-radius: 16px;
    background: var(--bg);
    border: 1px solid var(--border);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text);
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .cat-filter-pill:hover, .cat-filter-pill.active {
    background: var(--red);
    color: #ffffff;
    border-color: var(--red);
  }

  .latest-card-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
  }
  @media (min-width: 580px) { .latest-card-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (min-width: 980px) { .latest-card-grid { grid-template-columns: repeat(3, 1fr); } }

  .latest-news-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-s);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  }
  .latest-news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.14);
    border-color: rgba(225, 29, 72, 0.4);
  }

  .latest-thumb-wrap {
    position: relative;
    width: 100%;
    height: 190px;
    overflow: hidden;
    background: #0f172a;
  }
  .latest-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
  }
  .latest-news-card:hover .latest-thumb-img {
    transform: scale(1.06);
  }

  .latest-card-body {
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0.55rem;
  }

  .latest-card-title {
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 1.05rem;
    line-height: 1.35;
    color: var(--text);
    margin: 0;
  }
  .latest-card-title a {
    color: var(--text);
    text-decoration: none;
    transition: color 0.15s ease;
  }
  .latest-news-card:hover .latest-card-title a {
    color: var(--red);
  }

  .latest-card-excerpt {
    font-size: 0.83rem;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: auto;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .latest-card-foot {
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

  /* ===== 10. NATIVE MOBILE APP FLOATING BOTTOM BAR ===== */
  .mobile-app-bottom-bar {
    display: none;
  }
  @media (max-width: 768px) {
    body {
      padding-bottom: calc(72px + env(safe-area-inset-bottom, 12px)) !important;
    }
    .mobile-app-bottom-bar {
      display: flex;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 99999;
      background: rgba(9, 13, 22, 0.92);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.4);
      padding: 6px 12px calc(8px + env(safe-area-inset-bottom, 8px)) 12px;
      align-items: center;
      justify-content: space-around;
    }
    [data-theme="light"] .mobile-app-bottom-bar {
      background: rgba(255, 255, 255, 0.94);
      border-top: 1px solid rgba(0, 0, 0, 0.08);
      box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.08);
    }
    .app-bar-tab {
      background: none;
      border: none;
      color: #94a3b8;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      font-family: var(--font-display);
      font-size: 0.68rem;
      font-weight: 700;
      cursor: pointer;
      padding: 4px 10px;
      border-radius: 12px;
      text-decoration: none;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .app-bar-tab svg {
      transition: transform 0.2s ease, color 0.2s ease;
    }
    .app-bar-tab:active {
      transform: scale(0.9);
    }
    .app-bar-tab.active, .app-bar-tab:hover {
      color: var(--red);
    }
    .app-bar-tab.active svg {
      transform: translateY(-2px);
      color: var(--red);
    }
    [data-theme="light"] .app-bar-tab {
      color: #64748b;
    }
    [data-theme="light"] .app-bar-tab.active {
      color: var(--red);
    }
  }
</style>

<!-- NATIVE MOBILE APP CATEGORY QUICK-RIBBON (TOUCH-SCROLLABLE) -->
<?php if (!empty($ribbon_categories)): ?>
  <nav class="mobile-app-category-ribbon" aria-label="Catégories rapides">
    <a href="index.php" class="app-cat-pill active">
      <span class="app-cat-dot"></span> Tout
    </a>
    <?php foreach ($ribbon_categories as $rcat): ?>
      <a href="category.php?slug=<?php echo urlencode($rcat['slug']); ?>" class="app-cat-pill">
        <?php echo sanitize($rcat['name']); ?>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<!-- SECTION 1: PRESTIGE EDITORIAL HERO SHOWCASE & CURATED WIRE -->
<?php if (!empty($featured_news)): ?>
  <div class="hero-layout">
    
    <!-- Left Column: Main Hero Spotlight Slider with Touch Swipe -->
    <div class="hero-slider-box" id="heroSliderBox">
      
      <!-- Native Mobile App Story Segments across top -->
      <div class="hero-story-segments">
        <?php foreach ($featured_news as $s_idx => $s_item): ?>
          <div class="story-segment-bar <?php echo $s_idx === 0 ? 'active' : ''; ?>" data-story-segment="<?php echo $s_idx; ?>">
            <div class="story-segment-fill"></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Prev/Next Slider Arrow Controls (Desktop) -->
      <button type="button" class="hero-arrow-btn hero-arrow-prev" id="heroPrevBtn" aria-label="Article précédent">‹</button>
      <button type="button" class="hero-arrow-btn hero-arrow-next" id="heroNextBtn" aria-label="Article suivant">›</button>

      <!-- Mobile Touch Swipe Hint Capsule -->
      <span class="mobile-swipe-badge" id="heroSwipeBadge">1 / <?php echo count($featured_news); ?></span>

      <?php foreach ($featured_news as $index => $item): ?>
        <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>" 
           class="hero-slide-item <?php echo ($index === 0) ? 'active' : ''; ?>" 
           data-slide-index="<?php echo $index; ?>">
          
          <?php $slide_img = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1200&q=80'; ?>
          <img src="<?php echo sanitize($slide_img); ?>" alt="<?php echo sanitize($item['title']); ?>" class="hero-media-img" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
          
          <div class="hero-slide-overlay">
            <div class="hero-badges-row">
              <span class="cat-badge" style="background:<?php echo sanitize($item['color_code'] ?: 'var(--red)'); ?>">
                <span style="width:6px;height:6px;border-radius:50%;background:#fff"></span>
                <?php echo sanitize($item['category_name']); ?>
              </span>
              <?php if (!empty($item['is_breaking'])): ?>
                <span class="breaking-hero-badge">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                  BREAKING NEWS
                </span>
              <?php endif; ?>
            </div>

            <?php if (!empty($item['sub_title'])): ?>
              <div class="hero-kicker">
                <?php echo sanitize($item['sub_title']); ?>
              </div>
            <?php endif; ?>

            <h1 class="lead-title"><?php echo sanitize($item['title']); ?></h1>
            
            <div class="lead-meta">
              <?php if (!empty($item['author_avatar'])): ?>
                <img src="<?php echo sanitize($item['author_avatar']); ?>" class="hero-author-avatar" alt="">
              <?php endif; ?>
              <span class="lead-meta-item">
                <b><?php echo sanitize($item['author_name'] ?: 'Rédaction CPL'); ?></b>
              </span>
              <span class="lead-meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php echo $item['read_time_minutes']; ?> min de lecture
              </span>
              <span class="lead-meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?php echo time_ago($item['published_at']); ?>
              </span>
              <span class="lead-meta-item">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php echo number_format($item['views']); ?> vues
              </span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Right Column: Curated Newsroom Wire (Desktop Only, hidden on mobile to avoid story repetition) -->
    <div class="sidelist-column-wrap">
      <aside class="featured-sidelist-box">
        <div class="sidelist-head">
          <span class="sidelist-head-title">
            <span class="sidelist-head-dot"></span>
            À LA UNE • SÉLECTION
          </span>
          <span class="sidelist-counter-badge" id="playlistCounter">1 / <?php echo count($featured_news); ?></span>
        </div>

        <?php foreach ($featured_news as $index => $item): ?>
          <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>" 
             class="sidelist-item <?php echo ($index === 0) ? 'active-sidelist' : ''; ?>" 
             data-slide-trigger="<?php echo $index; ?>">
            <span class="sidelist-num"><?php echo sprintf('%02d', $index + 1); ?></span>
            
            <?php $side_img = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=200&q=80'; ?>
            <div class="sidelist-thumb-wrap">
              <img src="<?php echo sanitize($side_img); ?>" alt="" class="sidelist-thumb" loading="lazy">
            </div>
            
            <div class="sidelist-info">
              <span class="sidelist-cat-pill" style="color:<?php echo sanitize($item['color_code'] ?: 'var(--red)'); ?>">
                <?php echo sanitize($item['category_name']); ?>
              </span>
              <div class="sidelist-item-title">
                <?php echo sanitize(short_text($item['title'], 55)); ?>
              </div>
              <div class="sidelist-meta-row">
                <span><?php echo time_ago($item['published_at']); ?></span>
                <span>•</span>
                <span><?php echo number_format($item['views']); ?> vues</span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </aside>
    </div>

  </div>
<?php endif; ?>

<!-- TRENDING HASHTAGS TOPIC BAR -->
<?php if (!empty($popular_tags)): ?>
  <div class="trending-tags-bar">
    <span class="tags-bar-label">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
      SUJETS TENDANCES :
    </span>
    <div class="tags-bar-list">
      <?php foreach ($popular_tags as $tag): ?>
        <a href="search.php?q=<?php echo urlencode($tag['name']); ?>" class="tag-chip">
          #<?php echo sanitize($tag['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- SECTION 2: LES CHOIX DE LA RÉDACTION (SELECTION EXCLUSIVE) -->
<?php if (!empty($editors_picks)): ?>
  <section class="editors-section">
    <div class="section-header-bar">
      <div>
        <h2 class="section-header-title">LES CHOIX DE LA RÉDACTION</h2>
        <span class="section-header-sub">Sélection exclusive d'enquêtes et de grands reportages</span>
      </div>
    </div>

    <div class="editors-grid">
      <?php foreach ($editors_picks as $ep): ?>
        <a href="article.php?id=<?php echo $ep['id']; ?>&slug=<?php echo urlencode($ep['slug']); ?>" class="editors-card">
          <div class="editors-thumb-wrap">
            <?php $ep_img = !empty($ep['image_url']) ? $ep['image_url'] : 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=600&q=80'; ?>
            <img src="<?php echo sanitize($ep_img); ?>" alt="<?php echo sanitize($ep['title']); ?>" class="editors-thumb-img">
            
            <span class="cat-badge" style="position:absolute;top:10px;left:10px;background:<?php echo sanitize($ep['color_code'] ?: '#ef4444'); ?>">
              <?php echo sanitize($ep['category_name']); ?>
            </span>
          </div>

          <div class="editors-card-body">
            <h3 class="editors-card-title"><?php echo sanitize($ep['title']); ?></h3>
            
            <p class="editors-card-excerpt">
              <?php echo sanitize($ep['excerpt'] ?: short_text(strip_tags($ep['content']), 95)); ?>
            </p>

            <div class="editors-card-foot">
              <span>Par <b><?php echo sanitize($ep['author_name'] ?: 'Rédaction CPL'); ?></b></span>
              <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php echo $ep['read_time_minutes']; ?> min de lecture
              </span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- SECTION 3: ESPACE VIDÉO & GRANDS REPORTAGES (LECTURE INTERACTIVE ET MODALE) -->
<?php if (!empty($video_stories)): ?>
  <section class="broadcast-section">
    <div class="broadcast-head">
      <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap">
        <span class="broadcast-pill">
          <span style="width:7px;height:7px;border-radius:50%;background:#fff;display:inline-block;animation:pulseLive 1.5s infinite"></span>
          EN DIRECT & REPLAYS
        </span>
        <h3 class="broadcast-title">ESPACE VIDÉO & REPORTAGES</h3>
      </div>
    </div>

    <div class="broadcast-grid">
      <?php foreach ($video_stories as $vs): ?>
        <?php 
          $vs_url = sanitize($vs['video_url']);
          $vs_title = sanitize($vs['title']);
          $vs_cat = sanitize($vs['category_name']);
          $vs_color = sanitize($vs['color_code'] ?: '#ef4444');
          $vs_article = "article.php?id=" . $vs['id'] . "&slug=" . urlencode($vs['slug']);
        ?>
        <article class="video-card">
          
          <!-- Miniature vidéo interactive -->
          <div class="video-thumb-box" onclick="openVideoModal('<?php echo addslashes($vs_url); ?>', '<?php echo addslashes($vs_title); ?>', '<?php echo addslashes($vs_cat); ?>', '<?php echo addslashes($vs_color); ?>', '<?php echo addslashes($vs_article); ?>')">
            <img src="<?php echo sanitize($vs['image_url'] ?: 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=600&q=80'); ?>" alt="<?php echo $vs_title; ?>" class="video-thumb-img">
            
            <div class="video-play-btn" title="Lancer la vidéo">▶</div>

            <span class="cat-badge" style="position:absolute;top:10px;left:10px;background:<?php echo $vs_color; ?>">
              <?php echo $vs_cat; ?>
            </span>
          </div>

          <div class="video-card-body">
            <h4 class="video-card-title"><?php echo $vs_title; ?></h4>
            
            <div class="video-card-meta">
              <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?php echo time_ago($vs['published_at']); ?>
              </span>
              <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php echo number_format($vs['views']); ?> vues
              </span>
            </div>

            <!-- Boutons d'action en français -->
            <div class="video-action-bar">
              <button type="button" class="v-btn-play" onclick="openVideoModal('<?php echo addslashes($vs_url); ?>', '<?php echo addslashes($vs_title); ?>', '<?php echo addslashes($vs_cat); ?>', '<?php echo addslashes($vs_color); ?>', '<?php echo addslashes($vs_article); ?>')">
                ▶ Regarder la vidéo
              </button>
              <a href="<?php echo $vs_article; ?>" class="v-btn-details">
                Fiche article
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- MODALE DE LECTURE VIDÉO INTÉGRÉE (EN FRANÇAIS) -->
<div id="videoPlayerModal" class="video-modal-overlay">
  <div class="video-modal-container">
    <div class="video-modal-header">
      <div class="video-modal-title-wrap">
        <span id="vModalCat" class="cat-badge">Rubrique</span>
        <div id="vModalTitle" class="video-modal-headline">Titre de la vidéo</div>
      </div>
      <button type="button" id="closeVideoModal" class="video-modal-close" aria-label="Fermer la vidéo">✕</button>
    </div>

    <!-- Conteneur lecteur vidéo -->
    <div id="videoModalPlayerBox" class="video-modal-player-box"></div>

    <div class="video-modal-footer">
      <a id="vModalDetailLink" href="#" class="video-modal-detail-btn">Voir l'article complet →</a>
    </div>
  </div>
</div>

<!-- SECTION 4: CHRONIQUES & OPINIONS -->
<?php if (!empty($opinion_news)): ?>
  <section class="opinion-section">
    <div class="section-header-bar">
      <div>
        <h2 class="section-header-title">OPINIONS & CHRONIQUES</h2>
        <span class="section-header-sub">Analyses, débats et points de vue de nos éditorialistes</span>
      </div>
    </div>

    <div class="opinion-grid">
      <?php foreach ($opinion_news as $op): ?>
        <a href="article.php?id=<?php echo $op['id']; ?>&slug=<?php echo urlencode($op['slug']); ?>" class="opinion-card">
          <div class="opinion-author-wrap">
            <img src="<?php echo sanitize($op['author_avatar'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80'); ?>" class="opinion-author-avatar" alt="Éditorialiste">
            <div>
              <div class="opinion-author-name"><?php echo sanitize($op['author_name'] ?: 'Grand Éditorialiste'); ?></div>
              <div class="opinion-author-title">Éditorialiste CPL NEWS</div>
            </div>
          </div>

          <h3 class="opinion-quote-title">« <?php echo sanitize($op['title']); ?> »</h3>

          <div style="font-size:0.75rem;color:var(--dim);margin-top:auto;display:flex;justify-content:space-between">
            <span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?php echo $op['read_time_minutes']; ?> min de lecture
            </span>
            <span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?php echo time_ago($op['published_at']); ?>
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- SECTION 5: TOUTES LES ACTUALITÉS EN CONTINU -->
<section class="latest-section">
  <div class="section-header-bar">
    <div>
      <h2 class="section-header-title">TOUTES LES ACTUALITÉS EN CONTINU</h2>
      <span class="section-header-sub">Couverture internationale en temps réel par la rédaction</span>
    </div>

    <!-- Filtres par rubriques -->
    <div class="latest-cat-pills">
      <a href="index.php" class="cat-filter-pill active">Toutes les rubriques</a>
      <?php foreach (array_slice($all_categories, 0, 5) as $cp): ?>
        <a href="category.php?slug=<?php echo urlencode($cp['slug']); ?>" class="cat-filter-pill">
          <?php echo sanitize($cp['name']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($grid_news)): ?>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:2.5rem;text-align:center;color:var(--muted)">
      <h3>Aucun article publié pour le moment.</h3>
      <p style="font-size:.85rem;margin-top:6px">Consultez nos rubriques pour explorer les derniers dossiers.</p>
    </div>
  <?php else: ?>
    <div class="latest-card-grid">
      <?php foreach ($grid_news as $item): ?>
        <article class="latest-news-card">
          
          <div class="latest-thumb-wrap">
            <?php $item_img = !empty($item['image_url']) ? $item['image_url'] : 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?auto=format&fit=crop&w=600&q=80'; ?>
            <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>">
              <img src="<?php echo sanitize($item_img); ?>" alt="<?php echo sanitize($item['title']); ?>" class="latest-thumb-img">
            </a>
            
            <span class="cat-badge" style="position:absolute;top:10px;left:10px;background:<?php echo sanitize($item['color_code'] ?: '#ef4444'); ?>">
              <?php echo sanitize($item['category_name']); ?>
            </span>
            
            <?php if (!empty($item['video_url'])): ?>
              <span class="cat-badge" style="position:absolute;top:10px;right:10px;background:var(--blue)">
                Vidéo
              </span>
            <?php endif; ?>
          </div>

          <div class="latest-card-body">
            <h3 class="latest-card-title">
              <a href="article.php?id=<?php echo $item['id']; ?>&slug=<?php echo urlencode($item['slug']); ?>">
                <?php echo sanitize($item['title']); ?>
              </a>
            </h3>
            
            <p class="latest-card-excerpt">
              <?php echo sanitize($item['excerpt'] ?: short_text(strip_tags($item['content']), 110)); ?>
            </p>

            <div class="latest-card-foot">
              <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php echo $item['read_time_minutes']; ?> min de lecture • <?php echo time_ago($item['published_at']); ?>
              </span>
              
              <div style="display:flex;align-items:center;gap:.6rem">
                <span>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:-1px;margin-right:2px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <?php echo number_format($item['views']); ?>
                </span>
                
                <button type="button" class="latest-like-btn" onclick="likeArticle(<?php echo $item['id']; ?>, this)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3v11z"/></svg> 
                  <span class="like-cnt"><?php echo number_format($item['likes']); ?></span>
                </button>
              </div>
            </div>
          </div>

        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Fast Instant Optimistic Like Article AJAX Engine (0ms delay like Facebook) -->
<script>
function likeArticle(newsId, btn) {
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

// Hero Slider & Interactive Right Sidelist Playlist Sync Engine
document.addEventListener('DOMContentLoaded', () => {
  const slides = document.querySelectorAll('.hero-slide-item');
  const sidelistItems = document.querySelectorAll('.sidelist-item');
  const storySegments = document.querySelectorAll('.story-segment-bar');
  const counterEl = document.getElementById('playlistCounter');
  const swipeBadge = document.getElementById('heroSwipeBadge');
  const prevBtn = document.getElementById('heroPrevBtn');
  const nextBtn = document.getElementById('heroNextBtn');
  const sliderBox = document.getElementById('heroSliderBox');
  
  let currentSlide = 0;
  let slideTimer = null;

  function goToSlide(index) {
    if (slides.length === 0) return;
    currentSlide = (index + slides.length) % slides.length;

    slides.forEach((slide, i) => {
      slide.classList.toggle('active', i === currentSlide);
    });

    sidelistItems.forEach((item, i) => {
      item.classList.toggle('active-sidelist', i === currentSlide);
      if (i === currentSlide && window.innerWidth < 960) {
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
    });

    storySegments.forEach((bar, i) => {
      bar.classList.toggle('active', i === currentSlide);
      bar.classList.toggle('passed', i < currentSlide);
    });

    if (counterEl) {
      counterEl.textContent = (currentSlide + 1) + ' / ' + slides.length;
    }
    if (swipeBadge) {
      swipeBadge.textContent = (currentSlide + 1) + ' / ' + slides.length;
    }
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      goToSlide(currentSlide - 1);
      resetAutoTimer();
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      goToSlide(currentSlide + 1);
      resetAutoTimer();
    });
  }

  sidelistItems.forEach((item, idx) => {
    item.addEventListener('click', (e) => {
      if (window.innerWidth >= 960) {
        // Desktop: clicking item previews in slider
        e.preventDefault();
        goToSlide(idx);
        resetAutoTimer();
      }
      // On mobile, let clicking open the article directly or preview if tapped
    });
  });

  // Native Mobile Touch Swipe Gestures
  let touchStartX = 0;
  let touchStartY = 0;
  let touchEndX = 0;
  let touchEndY = 0;

  if (sliderBox) {
    sliderBox.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });

    sliderBox.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      touchEndY = e.changedTouches[0].screenY;
      const diffX = touchEndX - touchStartX;
      const diffY = touchEndY - touchStartY;
      if (Math.abs(diffX) > 35 && Math.abs(diffX) > Math.abs(diffY)) {
        if (diffX < 0) {
          goToSlide(currentSlide + 1); // Swiped Left -> Next Story
        } else {
          goToSlide(currentSlide - 1); // Swiped Right -> Prev Story
        }
        resetAutoTimer();
      }
    }, { passive: true });
  }

  function startAutoTimer() {
    slideTimer = setInterval(() => {
      goToSlide(currentSlide + 1);
    }, 5500);
  }

  function resetAutoTimer() {
    clearInterval(slideTimer);
    startAutoTimer();
  }

  if (slides.length > 1) {
    startAutoTimer();
    if (sliderBox) {
      sliderBox.addEventListener('mouseenter', () => clearInterval(slideTimer));
      sliderBox.addEventListener('mouseleave', () => resetAutoTimer());
    }
  }

  // In-Page Video Player Modal Engine
  const vModal = document.getElementById('videoPlayerModal');
  const vCloseBtn = document.getElementById('closeVideoModal');
  const vPlayerBox = document.getElementById('videoModalPlayerBox');
  const vTitle = document.getElementById('vModalTitle');
  const vCat = document.getElementById('vModalCat');
  const vDetailLink = document.getElementById('vModalDetailLink');

  window.openVideoModal = function(url, title, cat, color, articleUrl) {
    if (!vModal || !vPlayerBox) return;
    
    vTitle.textContent = title;
    vCat.textContent = cat;
    if (color) vCat.style.background = color;
    vDetailLink.href = articleUrl;

    if (url.includes('youtube.com') || url.includes('youtu.be')) {
      let embedUrl = url.replace('watch?v=', 'embed/').replace('youtu.be/', 'youtube.com/embed/');
      vPlayerBox.innerHTML = `<iframe src="${embedUrl}?autoplay=1" width="100%" height="100%" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
    } else {
      vPlayerBox.innerHTML = `<video src="${url}" controls autoplay style="width:100%;height:100%;object-fit:contain"></video>`;
    }

    vModal.classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  function closeVideoModal() {
    if (vModal) {
      vModal.classList.remove('active');
      if (vPlayerBox) vPlayerBox.innerHTML = '';
      document.body.style.overflow = '';
    }
  }

  if (vCloseBtn) vCloseBtn.addEventListener('click', closeVideoModal);
  if (vModal) {
    vModal.addEventListener('click', (e) => {
      if (e.target === vModal) closeVideoModal();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && vModal && vModal.classList.contains('active')) {
      closeVideoModal();
    }
  });
});
</script>

<!-- NATIVE MOBILE APP FLOATING BOTTOM BAR -->
<nav class="mobile-app-bottom-bar" aria-label="Barre d'actions mobile">
  <a href="index.php" class="app-bar-tab active">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span>Accueil</span>
  </a>
  <button type="button" class="app-bar-tab" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    <span>À la Une</span>
  </button>
  <button type="button" class="app-bar-tab" onclick="document.querySelector('.broadcast-section')?.scrollIntoView({behavior:'smooth'})">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor"/></svg>
    <span>Vidéos</span>
  </button>
  <a href="search.php" class="app-bar-tab">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <span>Recherche</span>
  </a>
  <button type="button" class="app-bar-tab" onclick="toggleTheme()" id="mobileBottomThemeBtn" aria-label="Changer le thème">
    <svg class="mobile-bottom-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
    <svg class="mobile-bottom-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    <span>Thème</span>
  </button>
</nav>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
