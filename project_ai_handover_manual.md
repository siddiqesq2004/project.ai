# Project AI Transfer & Integration Manual

This handover manual describes how to deploy the **Project AI** system on your new domain and integrate it as a premium feature card alongside **NEET AI**, **Marketing AI**, and others on your main landing page.

---

## 📂 1. Directory Structure & Files to Share

To deploy **Project AI** successfully on the new server, share these two main folders with your teammate. They should upload them to the root `public_html` directory of the domain:

```text
public_html/
├── api/                             <-- Upload the entire "api/" folder here
│   ├── uploads/
│   │   └── database.json            <-- Self-generating JSON database
│   ├── auth.php
│   ├── db.php
│   ├── extend_credits.php
│   ├── finalize.php
│   ├── generate_chapter.php
│   ├── generate_outline.php
│   ├── get_phase_data.php
│   ├── projects.php
│   └── usage_stats.php
│
└── project-ai/                      <-- Create this folder and upload frontend/dist/ files here
    ├── assets/
    │   ├── index-9CkSrI_0.js        <-- Compiled JS production bundle
    │   └── index-ufVLAIil.css        <-- Compiled CSS production bundle
    ├── index.html                   <-- Main entry HTML
    └── .htaccess                    <-- Routing rules
```

> [!IMPORTANT]
> **API Key Setup:**
> Inside the server's `api/get_phase_data.php`, `api/generate_outline.php`, and `api/generate_chapter.php`, ensure your **Anthropic Claude API Key** is configured correctly in the headers:
> ```php
> "x-api-key: YOUR_CLAUDE_API_KEY_HERE"
> ```

---

## 🎨 2. Add the "Project AI" Feature Card

To add a new **Project AI** card next to **NEET AI** on your dark landing page, copy and paste the following HTML & CSS code block into your landing page's source file (`index.html`, `index.php`, or similar):

### 🌐 HTML Code Snippet
Add this card block right next to your existing `NEET AI` card code:

```html
<!-- Project AI Card -->
<div class="ai-feature-card" onclick="window.location.href='/project-ai/'">
  <div class="card-banner-image">
    <!-- Premium futuristic academic simulation visual -->
    <img src="https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=600&q=80" alt="Project AI Academic Research" />
  </div>
  
  <div class="card-content-body">
    <!-- Icon Container -->
    <div class="card-icon-box">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#00f2fe" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c0 2 2.5 3 6 3s6-1 6-3v-5"/>
      </svg>
    </div>
    
    <!-- Title -->
    <h3 class="card-heading-title">Project AI</h3>
    
    <!-- Description -->
    <p class="card-description-text">
      Your premium AI academic co-pilot — formulate research problems, select methodologies, run simulations, and download ready-to-publish papers & PPT slides.
    </p>
  </div>
</div>
```

---

### 🎨 CSS Styling Code Snippet
If your teammate needs the exact CSS styling to match the gorgeous neon dark aesthetics shown in your shared image, they can use this:

```css
/* Container for the Grid */
.ai-features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  padding: 2rem 0;
}

/* Base Card Styling */
.ai-feature-card {
  background: rgba(15, 23, 42, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  overflow: hidden;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
}

.ai-feature-card:hover {
  transform: translateY(-8px);
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(0, 242, 254, 0.35);
  box-shadow: 0 15px 35px rgba(0, 242, 254, 0.15);
}

/* Banner Image at Top of Card */
.card-banner-image {
  width: 100%;
  height: 160px;
  overflow: hidden;
  position: relative;
}

.card-banner-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.85;
  transition: transform 0.5s ease;
}

.ai-feature-card:hover .card-banner-image img {
  transform: scale(1.08);
  opacity: 1;
}

/* Content Container */
.card-content-body {
  padding: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* Glowing Neon Icon Box */
.card-icon-box {
  width: 42px;
  height: 42px;
  background: rgba(0, 242, 254, 0.08);
  border: 1px solid rgba(0, 242, 254, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.ai-feature-card:hover .card-icon-box {
  background: rgba(0, 242, 254, 0.15);
  border-color: #00f2fe;
  box-shadow: 0 0 12px rgba(0, 242, 254, 0.3);
}

/* Card Heading Text */
.card-heading-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

/* Description Text */
.card-description-text {
  font-size: 0.9rem;
  line-height: 1.5;
  color: #94a3b8;
  margin: 0;
}
```

---

## ⚡ 3. Redirect Action Setup

1. **Subfolder Naming:**
   Make sure you name the frontend directory exactly `project-ai` on your server.
2. **Redirection Handler:**
   The `onclick="window.location.href='/project-ai/'"` trigger on the HTML card will automatically redirect the user to `yourdomain.com/project-ai/` upon clicking, launching the premium login/signup portal immediately!
