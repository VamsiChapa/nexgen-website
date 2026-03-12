/* ================================================================
   NEx-gEN Chatbot Knowledge Base
   ================================================================
   HOW TO UPDATE THIS FILE:
   ─────────────────────────
   • Each "intent" has:
       keywords : words the user might type (lowercase)
       responses: one or more reply texts (bot picks randomly)
       quickReplies (optional): suggestion chips shown after reply

   • To ADD a new topic  → copy any block below and add it to INTENTS
   • To EDIT an answer   → just change the text in "responses"
   • Save the file and reload the browser — done!
   ================================================================ */

const NEXGEN_BOT = {

  /* ── Bot identity ─────────────────────────────────────────────── */
  name: 'NEx-gEN Assistant',

  /* Inline SVG robot avatar — rendered in chat header circle */
  avatar: `<svg viewBox="0 0 32 32" width="26" height="26" xmlns="http://www.w3.org/2000/svg">
    <rect x="4" y="9" width="24" height="16" rx="4" fill="white" opacity="0.95"/>
    <circle cx="11.5" cy="17" r="2.5" fill="#e31e24"/>
    <circle cx="20.5" cy="17" r="2.5" fill="#e31e24"/>
    <rect x="11" y="21" width="10" height="2" rx="1" fill="#e31e24"/>
    <line x1="16" y1="4" x2="16" y2="9" stroke="white" stroke-width="2" stroke-linecap="round"/>
    <circle cx="16" cy="3" r="2" fill="white"/>
    <rect x="0" y="13" width="4" height="6" rx="2" fill="rgba(255,255,255,0.65)"/>
    <rect x="28" y="13" width="4" height="6" rx="2" fill="rgba(255,255,255,0.65)"/>
  </svg>`,

  /* ── Opening message ──────────────────────────────────────────── */
  greeting: `Hey there! 👋 I'm Nexie, the NEx-gEN virtual assistant 🤖\n\nI can help you with courses, fees, admission, location & more!\n\nWhat are you looking for today?`,

  /* ── Quick-reply chips on greeting ───────────────────────────── */
  openingChips: [
    '📚 Courses offered',
    '💰 Course fees',
    '📝 Admission process',
    '📍 Location & timings',
    '📞 Contact us',
  ],

  /* ── Fallback ─────────────────────────────────────────────────── */
  fallback: [
    "Hmm, I'm not sure about that 🤔 Try asking about courses, fees, or admission — or call us at 📞 +91 6301012437!",
    "That's a bit beyond what I know! For details reach us at 📞 6301012437 or WhatsApp us 💬",
    "I didn't quite catch that. Ask me about 📚 courses, 💰 fees, 📍 location, or 📝 how to join!",
  ],

  /* ══════════════════════════════════════════════════════════════
     INTENTS — add / edit freely below
     ══════════════════════════════════════════════════════════════ */
  intents: [

    /* ── Greetings ──────────────────────────────────────────────── */
    {
      id: 'greeting',
      keywords: ['hi', 'hello', 'hey', 'hii', 'helo', 'good morning', 'good afternoon', 'good evening', 'namaste', 'namaskar', 'howdy', 'greetings'],
      responses: [
        "Hello! 😊 Welcome to NEx-gEN School of Computers. How can I assist you today?",
        "Hi there! 👋 Great to see you! I'm Nexie — your NEx-gEN guide. What would you like to know?",
        "Hey! 🙌 You've reached NEx-gEN's virtual assistant. Ask me anything about courses or admission!",
      ],
      quickReplies: ['📚 Courses offered', '💰 Course fees', '📝 Admission process'],
    },

    /* ── Bye ────────────────────────────────────────────────────── */
    {
      id: 'bye',
      keywords: ['bye', 'goodbye', 'good bye', 'see you', 'see ya', 'later', 'take care', 'cya', 'farewell'],
      responses: [
        "Goodbye! 👋 Best of luck — hope to see you at NEx-gEN soon! 🏫",
        "Take care! 😊 Feel free to chat again whenever you need. All the best! 🌟",
        "Bye! 👋 Visit us at Opp. SP Office, Srikakulam — we'd love to meet you! 🤝",
      ],
      quickReplies: ['📚 Courses offered', '📞 Contact us'],
    },

    /* ── Thanks ─────────────────────────────────────────────────── */
    {
      id: 'thanks',
      keywords: ['thanks', 'thank you', 'thankyou', 'thank u', 'ok', 'okay', 'got it', 'understood', 'great', 'nice', 'good', 'awesome', 'perfect', 'cool'],
      responses: [
        "You're welcome! 😊 Feel free to ask anything else. Happy to help!",
        "Happy to help! 🙌 All the best for your career journey with NEx-gEN!",
        "Glad I could help! 😊 Visit us anytime at Srikakulam 🏫",
      ],
      quickReplies: ['📚 Courses offered', '📞 Contact us'],
    },

    /* ── About ──────────────────────────────────────────────────── */
    {
      id: 'about',
      keywords: ['about', 'who are you', 'nex gen', 'nexgen', 'institute', 'school', 'history', 'established', 'estd', 'founded', 'since', 'about nexgen'],
      responses: [
        "NEx-gEN School of Computers was established in 2007 in Srikakulam, AP. 🏫\n\nISO 9001:2015 certified with 18+ years of excellence, training 10,000+ students in IT, Accounts, Admin & Design.\n\nOur formula: Theory + Practicals + Assignment + Evaluation = 🎓 Certificate",
      ],
      quickReplies: ['📚 Courses offered', '🏫 Facilities', '📞 Contact us'],
    },

    /* ── ISO Certification ──────────────────────────────────────── */
    {
      id: 'iso',
      keywords: ['iso', 'certified', 'certification', 'quality', '9001', 'accredited', 'iso certified'],
      responses: [
        "Yes! NEx-gEN is ISO 9001:2015 Certified 🏆\n\nCert No.: 11301923\nIssued by: IQMS Certifications Pvt. Ltd.\nValid until: October 14, 2028\n\nScope: Quality Software, Hardware Training & IT Solutions.",
      ],
      quickReplies: ['📚 Courses offered', 'About NEx-gEN'],
    },

    /* ── All courses list ───────────────────────────────────────── */
    {
      id: 'courses',
      /* NOTE: Removed generic 'program/programs' keywords to avoid
         false matches with "Python Programming", "Java Programming" etc.
         Use specific intent keywords for individual courses. */
      keywords: ['courses offered', 'all courses', 'course list', 'what courses', 'list courses', 'which courses', 'what do you offer', 'curriculum', 'courses available', 'what can i learn'],
      responses: [
        "We offer the following courses 📚\n\n💻 PGDCA – Complete IT Professional Program\n💻 DCA – Fast-Track Computer Skills Diploma\n🧾 Tally Prime – Accounting & GST\n📊 MS-Office – Word, Excel, PowerPoint\n🐍 Python Programming\n☕ Java Programming\n⚙️ C Language\n🌐 HTML & Web Design\n🗄️ SQL / Database\n✍️ Handwriting Improvement (21 hrs)\n\nWhich course interests you?",
      ],
      quickReplies: ['PGDCA', 'DCA', 'Tally', 'Python', 'Java', '💰 Course fees'],
    },

    /* ── PGDCA ──────────────────────────────────────────────────── */
    {
      id: 'pgdca',
      keywords: ['pgdca', 'post graduate diploma', 'pg diploma', 'pgdca course', 'post graduate computer'],
      responses: [
        "📘 PGDCA — Complete IT Professional Program\n\n✅ Duration: 6 Months\n✅ Eligibility: Any Graduate\n✅ Course modules as per brochure\n✅ Covers: MS-Office, C, C++, Java, Python, HTML, SQL, Tally, Internet\n✅ Best for job seekers & government exam aspirants\n\nWant to know about fees or admission?",
      ],
      quickReplies: ['PGDCA fees', '📝 Admission process', '📞 Contact us'],
    },

    /* ── DCA ────────────────────────────────────────────────────── */
    {
      id: 'dca',
      keywords: ['dca', 'diploma in computer', 'dca course', 'diploma computer applications'],
      responses: [
        "📗 DCA — Fast-Track Computer Skills Diploma\n\n✅ Duration: 3 Months\n✅ Eligibility: 10th / 12th Pass\n✅ Course modules as per brochure\n✅ Covers: MS-Office, Internet, Tally basics, C Language intro\n✅ Perfect start for IT careers!\n\nWant details on fees or how to join?",
      ],
      quickReplies: ['DCA fees', '📝 Admission process', '📚 Other courses'],
    },

    /* ── Tally ──────────────────────────────────────────────────── */
    {
      id: 'tally',
      keywords: ['tally', 'tally prime', 'accounting course', 'gst course', 'accounts course', 'payroll', 'bookkeeping', 'tally course'],
      responses: [
        "🧾 Tally Prime Course\n\n✅ Duration: 3 Months\n✅ Eligibility: 10th Pass\n✅ Topics: Tally basics, GST filing, Payroll, Inventory, Balance Sheet, TDS\n✅ Used in 90% of Indian businesses — high demand!\n\nAsk about fees or admission 😊",
      ],
      quickReplies: ['Tally fees', '📝 Admission process', '📚 Other courses'],
    },

    /* ── MS-Office ──────────────────────────────────────────────── */
    {
      id: 'msoffice',
      keywords: ['ms office', 'microsoft office', 'ms-office', 'word excel', 'excel course', 'powerpoint course', 'ms office course', 'office course', 'word course'],
      responses: [
        "📊 MS-Office Course\n\n✅ Duration: 2–3 Months\n✅ Covers: Word, Excel, PowerPoint, Access, Outlook\n✅ Excel: Formulas, Pivot Tables, Charts, VLOOKUP\n✅ Essential for every office job in India!\n\nAsk us about batch timings and fees 😊",
      ],
      quickReplies: ['💰 Fees', '📝 Admission process', '📚 Other courses'],
    },

    /* ── Python ─────────────────────────────────────────────────── */
    {
      id: 'python',
      keywords: ['python', 'python programming', 'learn python', 'python course', 'scripting', 'automation', 'data science', 'python language'],
      responses: [
        "🐍 Python Programming\n\n✅ Duration: 45 Days\n✅ Eligibility: 12th Pass / Any Graduate\n✅ Topics: Python basics, OOP, File handling, Modules, Web scraping, Mini projects\n✅ Most in-demand language for jobs & freelancing 🔥\n\nAsk about fees or book a demo class!",
      ],
      quickReplies: ['Python fees', 'Java course', '📝 Admission process'],
    },

    /* ── Java ───────────────────────────────────────────────────── */
    {
      id: 'java',
      keywords: ['java', 'java programming', 'learn java', 'java course', 'object oriented', 'oops', 'oop', 'java language', 'core java'],
      responses: [
        "☕ Java Programming\n\n✅ Duration: 45 Days\n✅ Topics: Core Java, OOP, Collections, Exception Handling, JDBC basics\n✅ Used for Android apps, web backends & enterprise software\n✅ Tested in government IT exams (IBPS SO etc.)\n\nWant fees or admission details?",
      ],
      quickReplies: ['Java fees', 'Python course', '📝 Admission process'],
    },

    /* ── C Language ─────────────────────────────────────────────── */
    {
      id: 'clanguage',
      keywords: ['c language', 'c programming', 'c lang', 'learn c', 'c course', 'c programming language'],
      responses: [
        "⚙️ C Language\n\n✅ Duration: 2 Months\n✅ Topics: Variables, Loops, Arrays, Functions, Pointers, File I/O\n✅ Foundation for ALL programming 🧠\n✅ Ideal for engineering students & competitive exams",
      ],
      quickReplies: ['💰 Fees', '📚 Courses list', '📝 Admission process'],
    },

    /* ── HTML / Web Design ──────────────────────────────────────── */
    {
      id: 'html',
      keywords: ['html', 'html course', 'web design', 'web development', 'css', 'website', 'web design course', 'html and web', 'html css'],
      responses: [
        "🌐 HTML & CSS — Web Design\n\n✅ Duration: 1 Month\n✅ Topics: HTML5, CSS3, Responsive design, Basic JavaScript\n✅ Build your own website by the end! 🌟\n✅ Great for freelancing & web careers",
      ],
      quickReplies: ['💰 Fees', '📚 Courses list', '📝 Admission process'],
    },

    /* ── SQL / Database ─────────────────────────────────────────── */
    {
      id: 'sql',
      keywords: ['sql', 'database', 'mysql', 'dbms', 'sql course', 'database course', 'sql database', 'learn sql'],
      responses: [
        "🗄️ SQL / Database Course\n\n✅ Duration: 1 Month\n✅ Topics: SQL queries, Joins, Views, Stored procedures, MySQL basics\n✅ Essential for software developers & data analysts 📊",
      ],
      quickReplies: ['💰 Fees', '📚 Courses list', '📝 Admission process'],
    },

    /* ── Handwriting ────────────────────────────────────────────── */
    {
      id: 'handwriting',
      keywords: ['handwriting', 'hand writing', 'handwriting course', '21 hours', 'penmanship', 'improve handwriting', 'writing course'],
      responses: [
        "✍️ Handwriting Improvement Training\n\n✅ Duration: Just 21 Hours!\n✅ For: School students, college students, professionals\n✅ Improves speed, neatness & consistency\n✅ Very affordable — ask for current batch details 😊",
      ],
      quickReplies: ['💰 Fees', '📝 Admission process', '📚 Other courses'],
    },

    /* ── Fees ───────────────────────────────────────────────────── */
    {
      id: 'fees',
      keywords: ['fees', 'fee', 'cost', 'price', 'charges', 'how much', 'amount', 'payment', 'scholarship', 'discount', 'rate', 'course fee', 'fees structure'],
      responses: [
        "💰 For the latest fee structure, contact us directly:\n\n📞 Call / WhatsApp: +91 6301012437\n📧 Email: info.nexgensrikakulam@gmail.com\n\nFees vary by course & batch. We also offer:\n✅ Installment options\n✅ Early admission discounts\n✅ Group & sibling discounts\n\nWhich course are you interested in?",
      ],
      quickReplies: ['PGDCA fees', 'Tally fees', '📞 Call us', '💬 WhatsApp us'],
    },

    /* ── Admission ──────────────────────────────────────────────── */
    {
      id: 'admission',
      keywords: ['admission', 'enroll', 'enrolment', 'join', 'register', 'registration', 'apply', 'how to join', 'new batch', 'how to enroll', 'how to register', 'admission process'],
      responses: [
        "📝 Admission at NEx-gEN is super simple!\n\n1️⃣ Visit us OR call +91 6301012437\n2️⃣ Choose your course\n3️⃣ Submit: ID proof + passport photo\n4️⃣ Pay fees (installment available 😊)\n5️⃣ Start next day!\n\n✅ New batches every month\n✅ Morning, Afternoon & Evening batches\n✅ No entrance exam required!",
      ],
      quickReplies: ['💰 Course fees', '📍 Location', '📞 Contact us'],
    },

    /* ── Location ───────────────────────────────────────────────── */
    {
      id: 'location',
      keywords: ['location', 'address', 'where', 'srikakulam', 'directions', 'map', 'find us', 'near', 'rtc', 'sp office', 'arts college', 'how to reach', 'where is nexgen'],
      responses: [
        "📍 NEx-gEN School of Computers\n\nOpp. SP Office, Arts College Road,\nNear RTC Complex,\nSrikakulam – 532001, AP\n\n🗺️ Landmark: Opp. SP Office, near RTC Bus Stand\n\nCall us & we'll guide you: 📞 +91 6301012437",
      ],
      quickReplies: ['⏰ Timings', '📞 Contact us', '📝 Admission process'],
    },

    /* ── Timings ────────────────────────────────────────────────── */
    {
      id: 'timings',
      keywords: ['timing', 'timings', 'time', 'hours', 'batch', 'morning batch', 'evening batch', 'schedule', 'open', 'working hours', 'when open', 'batch timings'],
      responses: [
        "🕐 Institute Batch Timings:\n\n🌅 Morning Batch: 8:00 AM – 12:00 Noon\n☀️ Afternoon Batch: 12:00 Noon – 4:00 PM\n🌆 Evening Batch: 4:00 PM – 8:00 PM\n\n📅 Open: Monday to Saturday\n\nCall to confirm batch availability 😊",
      ],
      quickReplies: ['📝 Admission process', '📍 Location', '📞 Contact us'],
    },

    /* ── Facilities ─────────────────────────────────────────────── */
    {
      id: 'facilities',
      keywords: ['facilities', 'features', 'lab', 'computer lab', 'ac', 'air condition', 'infrastructure', 'amenities', 'power', 'wifi', 'internet', 'what facilities'],
      responses: [
        "🏫 NEx-gEN Facilities:\n\n❄️ Fully Air Conditioned classrooms\n🖥️ State-of-the-art Computer Lab\n⚡ 24-hour Power Supply (generator backup)\n👩‍🏫 Experienced Faculty\n🎯 100% Practical Orientation\n🤝 Placement Assistance\n🔁 Lifetime Support\n📜 ISO 9001:2015 Certified",
      ],
      quickReplies: ['📚 Courses offered', '📝 Admission process', 'About NEx-gEN'],
    },

    /* ── Placement ──────────────────────────────────────────────── */
    {
      id: 'placement',
      keywords: ['placement', 'job', 'jobs', 'career', 'employment', 'salary', 'work', 'opportunity', 'job placement', 'career guidance', 'get job'],
      responses: [
        "💼 Placement at NEx-gEN:\n\n✅ Active placement assistance\n✅ Tie-ups with local IT companies & businesses\n✅ Resume building & interview prep\n✅ 80%+ students placed within 3–6 months 🚀\n\nLifetime support — even after your course ends!",
      ],
      quickReplies: ['📚 Courses offered', '📝 Admission process', '📞 Contact us'],
    },

    /* ── Contact ────────────────────────────────────────────────── */
    {
      id: 'contact',
      keywords: ['contact', 'phone', 'call', 'mobile', 'number', 'email', 'reach', 'whatsapp', 'message', 'talk', 'speak', 'contact us', 'get in touch'],
      responses: [
        "📞 Contact NEx-gEN:\n\n📱 Mobile / WhatsApp: +91 6301012437\n📧 Email: info.nexgensrikakulam@gmail.com\n🌐 Website: www.nex-gen.in\n📸 Instagram: @info.nexgensrikakulam\n\n📍 Opp. SP Office, Arts College Road,\nSrikakulam – 532001",
      ],
      quickReplies: ['📞 Call us', '💬 WhatsApp us', '📍 Location'],
    },

    /* ── Call / WhatsApp ────────────────────────────────────────── */
    {
      id: 'call',
      keywords: ['call us', 'call now', 'ring', 'telephone', 'phone number'],
      responses: ["📞 Call us directly:\n+91 6301012437\n\nAvailable Monday–Saturday, 8 AM – 8 PM 😊"],
      quickReplies: ['💬 WhatsApp us', '📍 Location', '📚 Courses offered'],
    },
    {
      id: 'whatsapp',
      keywords: ['whatsapp us', 'whatsapp', 'chat on whatsapp', 'message us'],
      responses: ["💬 WhatsApp us directly:\nhttps://wa.me/916301012437\n\nOr message +91 6301012437 on WhatsApp 😊"],
      quickReplies: ['📞 Call us', '📍 Location', '📚 Courses offered'],
    },

    /* ── Certificate Verification ───────────────────────────────── */
    {
      id: 'certificate',
      keywords: ['certificate', 'verify certificate', 'check certificate', 'certificate number', 'download certificate', 'my certificate', 'get certificate', 'cert'],
      responses: [
        "🎓 Certificate Verification:\n\nVisit our Certificates page to verify and download your certificate!\n\n🔗 Click 'Certificates' in the top navigation menu.\n\nYou will need:\n✅ Your Certificate Number\n✅ Your Full Name (as on certificate)\n\nFor help: 📞 +91 6301012437",
      ],
      quickReplies: ['📞 Contact us', '📝 Admission process', '📚 Courses offered'],
    },

    /* ── Demo class ─────────────────────────────────────────────── */
    {
      id: 'demo',
      keywords: ['demo', 'demo class', 'trial class', 'free demo', 'free class', 'sample class', 'attend demo', 'watch demo'],
      responses: [
        "🎬 Want to attend a demo class?\n\nClick the '🎬 Watch Demo' button on any course card!\n\nOr book a live demo:\n📞 +91 6301012437\n💬 wa.me/916301012437\n\nFREE demo classes available! ✅",
      ],
      quickReplies: ['📞 Call us', '💬 WhatsApp us', '📝 Admission process'],
    },

  ], /* end intents */

}; /* end NEXGEN_BOT */
