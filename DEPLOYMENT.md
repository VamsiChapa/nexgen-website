# NEx-gEN Website — Deployment Guide
## Low-Cost Hosting Options for India

This is a **100% static website** (HTML + CSS + JS). No database, no server-side code.
That means hosting is either **free or very cheap**.

---

## Option 1 — GitHub Pages (FREE, Recommended for Beginners)

**Cost: ₹0/month**

### Steps

```bash
# 1. Install Git (if not already)
#    https://git-scm.com/download

# 2. Create a GitHub account at https://github.com (free)

# 3. Create a new repository named: nexgen-website
#    (Make it PUBLIC for free GitHub Pages)

# 4. In your project folder, run:
git init
git add .
git commit -m "Initial commit — NEx-gEN website"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/nexgen-website.git
git push -u origin main

# 5. In GitHub → Repository → Settings → Pages
#    Source: Deploy from branch → main → / (root) → Save

# 6. Your site goes live at:
#    https://YOUR_USERNAME.github.io/nexgen-website/
```

### Connect Custom Domain (www.nex-gen.in)

```bash
# 1. Create a file named CNAME in your project root:
echo "www.nex-gen.in" > CNAME

# 2. In your domain registrar (GoDaddy / BigRock / Google Domains):
#    Add a CNAME record:
#      Host: www
#      Points to: YOUR_USERNAME.github.io
#    Add A records (for apex domain nex-gen.in):
#      185.199.108.153
#      185.199.109.153
#      185.199.110.153
#      185.199.111.153

# 3. In GitHub → Pages → Custom domain → enter: www.nex-gen.in
# 4. Enable "Enforce HTTPS" checkbox
# DNS propagation takes 10–30 minutes to 24 hours.
```

---

## Option 2 — Netlify (FREE tier, drag-and-drop deploy)

**Cost: ₹0/month** (100 GB bandwidth/month free)

1. Go to [netlify.com](https://netlify.com) → Sign up free
2. Drag and drop your **entire project folder** onto the Netlify dashboard
3. Site goes live instantly with a random URL like `https://xyz-nexgen.netlify.app`
4. Add custom domain: Site settings → Domain management → Add domain → `www.nex-gen.in`
5. Netlify provides **free SSL (HTTPS)** automatically

### CLI Deploy (faster updates)

```bash
npm install -g netlify-cli
netlify login
netlify deploy --prod --dir .
```

---

## Option 3 — Hostinger India (Paid, Most Professional)

**Cost: ₹69–149/month** (cheapest shared hosting in India)

Best for: custom domain + email hosting (info@nex-gen.in)

1. Visit [hostinger.in](https://hostinger.in) → Single Web Hosting plan (~₹69/month)
2. Buy your domain `nex-gen.in` there too (~₹699/year for .in)
3. Upload files via **File Manager** in hPanel or use FTP:
   ```
   Host:     ftp.nex-gen.in
   Username: (from hostinger panel)
   Password: (from hostinger panel)
   ```
4. Upload all files to `public_html/` folder
5. SSL is free (Let's Encrypt) — enable in hPanel → SSL

---

## Option 4 — Cloudflare Pages (FREE, Fastest CDN)

**Cost: ₹0/month** — Global CDN with Indian PoPs (Mumbai, Chennai)

```bash
# 1. Push code to GitHub (see Option 1 steps 1–4)
# 2. Go to https://pages.cloudflare.com → Connect GitHub repo
# 3. Build settings:
#    Framework preset: None
#    Build command: (leave empty)
#    Build output directory: /
# 4. Deploy — get URL like https://nexgen.pages.dev
# 5. Add custom domain in Cloudflare DNS
```

---

## Recommendation Summary

| Option         | Cost/month | Difficulty | Best For                        |
|----------------|-----------|------------|----------------------------------|
| GitHub Pages   | ₹0        | Easy       | Starting out, free domain        |
| Netlify        | ₹0        | Easy       | Quick drag-drop, auto-deploys    |
| Cloudflare     | ₹0        | Medium     | Fastest loading across India     |
| Hostinger      | ~₹99      | Easy       | Professional + email hosting     |

**Our recommendation for NEx-gEN:** Start with **Netlify** (free, instant) and later move to **Hostinger** when you need a professional email (info@nex-gen.in).

---

## Updating the Website

After making changes to your files:

### GitHub Pages / Netlify (via Git)

```bash
git add .
git commit -m "Update: added new gallery photos"
git push
# Site updates automatically within 1–2 minutes
```

### Netlify (drag-and-drop)

Simply drag the updated project folder to the Netlify dashboard again.

### Hostinger (FTP)

Re-upload changed files via File Manager or FTP client (FileZilla).

---

## Adding Your Own Images

Place your images in these folders:

```
images/
  logo.png              ← Your institute logo (ideally transparent PNG)
  about.jpg             ← Building / classroom photo
  iso-certificate.jpg   ← ISO certificate scan
  iso-badge.png         ← ISO badge/logo (small circular)
  hero1.jpg             ← Hero banner image 1 (1920×900px ideal)
  hero2.jpg             ← Hero banner image 2
  hero3.jpg             ← Hero banner image 3
  gallery/
    campus1.jpg, campus2.jpg    ← Building photos
    lab1.jpg, lab2.jpg          ← Computer lab photos
    students1.jpg, students2.jpg ← Students at work
    event1.jpg, event2.jpg      ← Events / certificate distribution
```

**Image tips:**
- Compress images before uploading: use [squoosh.app](https://squoosh.app) (free, no signup)
- Hero images: 1920×900px, JPEG quality 80%
- Gallery images: 800×600px, JPEG quality 75%
- Logo: PNG with transparent background

---

## Contact Form → Google Sheets (FREE, Recommended)

Every enquiry from the website lands as a new row in your Google Sheet automatically.

### Step 1 — Add headers to your Google Sheet

Your sheet: https://docs.google.com/spreadsheets/d/153mDwcf5ltj68HjxZHSuGnFiAqw4VLTFT1M24KBwA2Q/edit

In **Row 1**, add these headers exactly (click each cell and type):
```
A1: Timestamp   B1: Name   C1: Phone   D1: Email   E1: Course   F1: Message
```

### Step 2 — Open Apps Script

In your Google Sheet: **Extensions → Apps Script**

Delete any existing code and paste this (your sheet ID is already filled in):

```javascript
var SHEET_ID = '153mDwcf5ltj68HjxZHSuGnFiAqw4VLTFT1M24KBwA2Q';

function doPost(e) {
  try {
    var sheet = SpreadsheetApp.openById(SHEET_ID).getActiveSheet();
    var data  = JSON.parse(e.postData.contents);

    sheet.appendRow([
      data.date    || new Date().toLocaleString(),
      data.name    || '',
      data.phone   || '',
      data.email   || '',
      data.course  || '',
      data.message || ''
    ]);

    return ContentService
      .createTextOutput(JSON.stringify({ status: 'ok' }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ status: 'error', message: err.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
```

### Step 3 — Deploy as Web App

1. Click **Deploy → New deployment**
2. Click the gear icon ⚙ next to "Type" → select **Web app**
3. Set:
   - Description: `NEx-gEN Form Handler`
   - Execute as: **Me**
   - Who has access: **Anyone**
4. Click **Deploy** → Authorise when prompted
5. Copy the **Web app URL** (looks like `https://script.google.com/macros/s/AKfy.../exec`)

### Step 4 — Paste URL into the website

Open `js/main.js` and replace:
```javascript
const SHEETS_URL = 'YOUR_APPS_SCRIPT_WEB_APP_URL_HERE';
```
with:
```javascript
const SHEETS_URL = 'https://script.google.com/macros/s/AKfycbz89AlSQw1b79McVocJjvRYn4cYqrNW9dkl-nIxiydwVFMDz_7_JCYoK1EiN0np3YVaWA/exec';
```

That's it — every enquiry will now appear as a new row in your sheet instantly.

> **Tip:** In the Google Sheet, go to **Tools → Notification rules** to get an email alert every time a new enquiry comes in.

---

## Contact Form — Making It Actually Send Emails (Alternative)

The form currently shows a success message but doesn't send email. To enable:

### Option A — Formspree (FREE, easiest)

1. Go to [formspree.io](https://formspree.io) → Sign up free
2. Create a new form → Copy your form ID (e.g. `xpzgabcd`)
3. In `js/main.js`, replace the `setTimeout` simulation block with:

```javascript
fetch('https://formspree.io/f/xpzgabcd', {
  method: 'POST',
  body: new FormData(form),
  headers: { 'Accept': 'application/json' }
}).then(res => {
  if (res.ok) {
    form.reset();
    formSuccess.classList.add('show');
    setTimeout(() => formSuccess.classList.remove('show'), 5000);
  }
}).catch(() => alert('Error sending. Please call us directly.'));
```

Free tier: 50 submissions/month. Paid: ₹830/month for unlimited.

### Option B — WhatsApp redirect (simplest, free)

Replace form submit with:
```javascript
const msg = `Name: ${name}%0APhone: ${phone}%0ACourse: ${$('#fcourse').value}`;
window.open(`https://wa.me/916301012437?text=${msg}`, '_blank');
```

---

## Google Maps — Update the Embed

1. Go to [Google Maps](https://maps.google.com)
2. Search: "NEx-gEN School of Computers Srikakulam"
3. Click Share → Embed a map → Copy HTML
4. In `index.html`, replace the `<iframe src="...">` in the Contact section with the copied embed code

---

## Maintenance Cost Summary

| Item                  | Cost (Approx.)     |
|-----------------------|--------------------|
| Domain (.in)          | ₹699–999/year      |
| Hosting (Hostinger)   | ₹69–149/month      |
| SSL Certificate       | FREE (Let's Encrypt)|
| Form handler (Formspree) | FREE (50/month) |
| **Total (minimum)**   | **~₹1,500/year**   |
| **Total (full setup)**| **~₹2,500–3,000/year** |

Using GitHub Pages / Netlify = **₹0 hosting**, only domain cost (~₹999/year).

---

## No Patent / Copyright Issues

This website is 100% original:
- No paid templates or themes used
- Font Awesome 6 (free, MIT-licensed)
- Google Fonts Poppins (SIL Open Font License)
- All code written from scratch
- No images copied from other websites (all images are yours to add)
