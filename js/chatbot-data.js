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
   • To ADD a new course → update the "courses" intent responses
     AND add a new intent for that course's details
   • Save the file and reload the browser — done!
   ================================================================ */

const NEXGEN_BOT = {

  /* ── Bot identity ─────────────────────────────────────────────── */
  name: 'NEx-gEN Assistant',
  avatar: 'NG',   /* shown in chat header when no logo */
  color: '#003087',

  /* ── Opening message when chat is first opened ────────────────── */
  greeting: `Hi! 👋 I'm the NEx-gEN virtual assistant.\nHow can I help you today?`,

  /* ── Quick-reply chips shown on greeting ──────────────────────── */
  openingChips: [
    'Courses offered',
    'Course fees',
    'Admission process',
    'Location & timings',
    'Contact us',
  ],

  /* ── Fallback when no intent matches ──────────────────────────── */
  fallback: [
    "I'm not sure about that. Please call us at 📞 +91 6301012437 or WhatsApp us — we'll be happy to help!",
    "That's a great question! For detailed info, reach us at 📞 6301012437 or email info.nexgensrikakulam@gmail.com",
    "I didn't quite catch that. Try asking about our courses, fees, location, or admission process.",
  ],

  /* ══════════════════════════════════════════════════════════════
     INTENTS — add / edit freely below
     ══════════════════════════════════════════════════════════════ */
  intents: [

    /* ── Greetings ──────────────────────────────────────────────── */
    {
      id: 'greeting',
      keywords: ['hi', 'hello', 'hey', 'hii', 'helo', 'good morning', 'good afternoon', 'good evening', 'namaste', 'namaskar'],
      responses: [
        "Hello! 😊 Welcome to NEx-gEN School of Computers. How can I assist you?",
        "Hi there! 👋 I'm here to help with any questions about NEx-gEN. What would you like to know?",
      ],
      quickReplies: ['Courses offered', 'Course fees', 'Admission process'],
    },

    /* ── About the institute ────────────────────────────────────── */
    {
      id: 'about',
      keywords: ['about', 'who are you', 'nex gen', 'nexgen', 'institute', 'school', 'history', 'established', 'estd', 'founded', 'since'],
      responses: [
        "NEx-gEN School of Computers was established in 2007 in Srikakulam, Andhra Pradesh. 🏫\n\nWe are an ISO 9001:2015 certified institute with 18+ years of excellence, training 5000+ students in IT, Accounts, Admin & Design.\n\nOur motto: Theory + Practicals + Assignment + Evaluation = Certificate ✅",
      ],
      quickReplies: ['Courses offered', 'Facilities', 'Contact us'],
    },

    /* ── ISO Certification ──────────────────────────────────────── */
    {
      id: 'iso',
      keywords: ['iso', 'certified', 'certification', 'quality', '9001', 'certificate', 'accredited'],
      responses: [
        "Yes! NEx-gEN is ISO 9001:2015 Certified 🏆\n\nCertificate No.: 11301923\nIssued by: IQMS Certifications Pvt. Ltd.\nValid until: October 14, 2028\n\nScope: Providing Quality Software, Hardware Training & IT Solutions.",
      ],
      quickReplies: ['Courses offered', 'About NEx-gEN'],
    },

    /* ── All courses list ───────────────────────────────────────── */
    {
      id: 'courses',
      keywords: ['course', 'courses', 'program', 'programmes', 'programs', 'what do you offer', 'syllabus', 'curriculum', 'learn', 'study', 'training', 'offer'],
      responses: [
        "We offer the following courses 📚\n\n💻 PGDCA – Post Graduate Diploma in Computer Applications\n💻 DCA – Diploma in Computer Applications\n🧾 Tally Prime – Accounting & GST\n📊 MS-Office – Word, Excel, PowerPoint\n🐍 Python Programming\n☕ Java Programming\n⚙️ C Language\n🌐 HTML & Web Design\n🗄️ SQL / Database\n✍️ Handwriting Improvement (21 hrs)\n\nWhich course would you like to know more about?",
      ],
      quickReplies: ['PGDCA', 'DCA', 'Tally', 'Python', 'Java', 'Course fees'],
    },

    /* ── PGDCA ──────────────────────────────────────────────────── */
    {
      id: 'pgdca',
      keywords: ['pgdca', 'post graduate diploma', 'pg diploma'],
      responses: [
        "📘 PGDCA — Post Graduate Diploma in Computer Applications\n\n✅ Duration: 1 Year\n✅ Eligibility: Any Graduate (BA/BSc/BCom/BE)\n✅ Syllabus covers: MS-Office, C, C++, Java, Python, HTML, SQL, Tally, Internet\n✅ Certificate: Institute + MSBTE/University recognised\n✅ Best for: Job seekers, government exam aspirants\n\nWant to know about fees or admission?",
      ],
      quickReplies: ['PGDCA fees', 'Admission process', 'Contact us'],
    },

    /* ── DCA ────────────────────────────────────────────────────── */
    {
      id: 'dca',
      keywords: ['dca', 'diploma in computer'],
      responses: [
        "📗 DCA — Diploma in Computer Applications\n\n✅ Duration: 6 Months\n✅ Eligibility: 10th Pass / 12th Pass\n✅ Syllabus: MS-Office, Internet, Tally basics, C Language intro\n✅ Perfect starting point for IT careers\n✅ Certificate awarded on completion\n\nWant details on fees or how to join?",
      ],
      quickReplies: ['DCA fees', 'Admission process', 'Other courses'],
    },

    /* ── Tally ──────────────────────────────────────────────────── */
    {
      id: 'tally',
      keywords: ['tally', 'tally prime', 'accounting', 'gst', 'accounts', 'payroll', 'bookkeeping'],
      responses: [
        "🧾 Tally Prime Course\n\n✅ Duration: 3 Months\n✅ Eligibility: 10th Pass\n✅ Topics: Tally basics, GST filing, Payroll, Inventory, Balance Sheet, TDS\n✅ Used in 90% of Indian businesses — high job demand!\n✅ Helps you work as an accounts executive or GST consultant\n\nInterested? Ask about fees or admission.",
      ],
      quickReplies: ['Tally fees', 'Admission process', 'Other courses'],
    },

    /* ── MS-Office ──────────────────────────────────────────────── */
    {
      id: 'msoffice',
      keywords: ['ms office', 'microsoft office', 'word', 'excel', 'powerpoint', 'ms-office'],
      responses: [
        "📊 MS-Office Course\n\n✅ Duration: 2–3 Months\n✅ Covers: Word, Excel, PowerPoint, Access, Outlook\n✅ Excel advanced: Formulas, Pivot Tables, Charts, VLOOKUP\n✅ Essential for every office job in India\n\nAsk us about batch timings and fees!",
      ],
      quickReplies: ['Fees', 'Admission process', 'Other courses'],
    },

    /* ── Python ─────────────────────────────────────────────────── */
    {
      id: 'python',
      keywords: ['python', 'scripting', 'automation', 'data science'],
      responses: [
        "🐍 Python Programming\n\n✅ Duration: 3–4 Months\n✅ Eligibility: 12th Pass / Any Graduate\n✅ Topics: Python basics, OOP, File handling, Modules, Web scraping intro, Mini projects\n✅ Most in-demand language in India for jobs & freelancing\n\nAsk about fees or schedule a demo class!",
      ],
      quickReplies: ['Python fees', 'Java course', 'Admission process'],
    },

    /* ── Java ───────────────────────────────────────────────────── */
    {
      id: 'java',
      keywords: ['java', 'object oriented', 'oops', 'oop'],
      responses: [
        "☕ Java Programming\n\n✅ Duration: 3–4 Months\n✅ Topics: Core Java, OOP concepts, Collections, Exception Handling, JDBC basics\n✅ Used for Android apps, web backends, and enterprise software\n✅ Widely tested in government IT exams (IBPS SO, etc.)\n\nWant to know fees or admission details?",
      ],
      quickReplies: ['Java fees', 'Python course', 'Admission process'],
    },

    /* ── C Language ─────────────────────────────────────────────── */
    {
      id: 'clanguage',
      keywords: ['c language', 'c programming', ' c ', 'programming language', 'c lang'],
      responses: [
        "⚙️ C Language\n\n✅ Duration: 2 Months\n✅ Topics: Variables, Loops, Arrays, Functions, Pointers, File I/O\n✅ Foundation for all programming — helps with competitive exams & placements\n✅ Ideal for engineering students",
      ],
      quickReplies: ['Fees', 'Courses list', 'Admission process'],
    },

    /* ── HTML / Web Design ──────────────────────────────────────── */
    {
      id: 'html',
      keywords: ['html', 'web design', 'web development', 'css', 'website'],
      responses: [
        "🌐 HTML & Web Design\n\n✅ Duration: 2 Months\n✅ Topics: HTML5, CSS3, Responsive design, Basic JavaScript\n✅ Build your own website by the end of the course!\n✅ Great for freelancing & starting a web career",
      ],
      quickReplies: ['Fees', 'Courses list', 'Admission process'],
    },

    /* ── SQL / Database ─────────────────────────────────────────── */
    {
      id: 'sql',
      keywords: ['sql', 'database', 'mysql', 'dbms', 'data'],
      responses: [
        "🗄️ SQL / Database Course\n\n✅ Duration: 1.5 Months\n✅ Topics: SQL queries, Joins, Views, Stored procedures, MySQL basics\n✅ Essential for any software developer or data analyst role",
      ],
      quickReplies: ['Fees', 'Courses list', 'Admission process'],
    },

    /* ── Handwriting ────────────────────────────────────────────── */
    {
      id: 'handwriting',
      keywords: ['handwriting', 'hand writing', 'writing', '21 hours', 'penmanship'],
      responses: [
        "✍️ Handwriting Improvement Training\n\n✅ Duration: Just 21 Hours!\n✅ Suitable for: School students, college students, professionals\n✅ Improves speed, neatness, and consistency\n✅ Very affordable — ask us for current batch details",
      ],
      quickReplies: ['Fees', 'Admission process', 'Other courses'],
    },

    /* ── Fees ───────────────────────────────────────────────────── */
    {
      id: 'fees',
      keywords: ['fee', 'fees', 'cost', 'price', 'charges', 'how much', 'amount', 'pay', 'payment', 'scholarship', 'discount', 'offer', 'rate'],
      responses: [
        "💰 For the latest fee structure, please contact us directly:\n\n📞 Call / WhatsApp: +91 6301012437\n📧 Email: info.nexgensrikakulam@gmail.com\n\nFees vary by course & batch. We also offer:\n✅ Installment payment options\n✅ Discounts for early admission\n✅ Group / sibling discounts\n\nWould you like details for a specific course?",
      ],
      quickReplies: ['PGDCA fees', 'Tally fees', 'Call us', 'WhatsApp us'],
    },

    /* ── Admission ──────────────────────────────────────────────── */
    {
      id: 'admission',
      keywords: ['admission', 'enroll', 'enrolment', 'join', 'register', 'registration', 'apply', 'how to join', 'intake', 'start', 'new batch'],
      responses: [
        "📝 Admission Process at NEx-gEN:\n\n1️⃣ Visit our institute OR call +91 6301012437\n2️⃣ Choose your course\n3️⃣ Submit documents: ID proof + passport photo\n4️⃣ Pay course fees (installment available)\n5️⃣ Start attending from the very next day!\n\n✅ New batches start every month\n✅ Morning & evening batches available\n✅ No entrance exam required",
      ],
      quickReplies: ['Course fees', 'Location', 'Contact us'],
    },

    /* ── Location ───────────────────────────────────────────────── */
    {
      id: 'location',
      keywords: ['location', 'address', 'where', 'place', 'srikakulam', 'directions', 'map', 'find', 'near', 'rtc', 'sp office', 'arts college'],
      responses: [
        "📍 NEx-gEN School of Computers\n\nOpp. SP Office, Arts College Road,\nNear RTC Complex,\nSrikakulam – 532001,\nAndhra Pradesh, India\n\n🗺️ Landmark: Opposite SP Office, near RTC Bus Stand\n\nCall us and we'll guide you: +91 6301012437",
      ],
      quickReplies: ['Timings', 'Contact us', 'Admission process'],
    },

    /* ── Timings ────────────────────────────────────────────────── */
    {
      id: 'timings',
      keywords: ['timing', 'timings', 'time', 'hours', 'batch', 'morning', 'evening', 'schedule', 'open', 'working hours', 'when'],
      responses: [
        "🕐 Institute Timings:\n\n🌅 Morning Batch: 9:00 AM – 12:00 PM\n☀️ Afternoon Batch: 12:00 PM – 3:00 PM\n🌆 Evening Batch: 4:00 PM – 7:00 PM\n\n📅 Open: Monday to Saturday\n\nChoose a batch that suits your schedule! Call us to confirm current batch availability.",
      ],
      quickReplies: ['Admission process', 'Location', 'Contact us'],
    },

    /* ── Facilities ─────────────────────────────────────────────── */
    {
      id: 'facilities',
      keywords: ['facilities', 'features', 'lab', 'computer lab', 'ac', 'air condition', 'infrastructure', 'amenities', 'power', 'wifi', 'internet'],
      responses: [
        "🏫 NEx-gEN Facilities:\n\n❄️ Fully Air Conditioned classrooms\n🖥️ State-of-the-art Computer Lab\n⚡ 24-hour Power Supply (generator backup)\n👩‍🏫 Highly Experienced Faculty\n🎯 100% Practical Orientation\n🤝 Placement Assistance\n💼 Career Guidance\n🔁 Lifetime Support\n📜 ISO 9001:2015 Certified Quality",
      ],
      quickReplies: ['Courses offered', 'Admission process', 'About NEx-gEN'],
    },

    /* ── Placement ──────────────────────────────────────────────── */
    {
      id: 'placement',
      keywords: ['placement', 'job', 'jobs', 'career', 'employment', 'hire', 'salary', 'work', 'opportunity', 'recruit'],
      responses: [
        "💼 Placement at NEx-gEN:\n\n✅ Active placement assistance for all students\n✅ Tie-ups with local IT companies and businesses\n✅ Resume building & interview preparation guidance\n✅ 90%+ of our students are placed within 3–6 months\n\nWe provide lifetime support — even after your course ends!",
      ],
      quickReplies: ['Courses offered', 'Admission process', 'Contact us'],
    },

    /* ── Contact ────────────────────────────────────────────────── */
    {
      id: 'contact',
      keywords: ['contact', 'phone', 'call', 'mobile', 'number', 'email', 'reach', 'whatsapp', 'message', 'talk', 'speak'],
      responses: [
        "📞 Contact NEx-gEN:\n\n📱 Mobile / WhatsApp: +91 6301012437\n📧 Email: info.nexgensrikakulam@gmail.com\n🌐 Website: www.nex-gen.in\n📘 Facebook: facebook.com/nexgensrikakulam\n\n📍 Opp. SP Office, Arts College Road,\nNear RTC Complex, Srikakulam – 532001",
      ],
      quickReplies: ['Call us', 'WhatsApp us', 'Location'],
    },

    /* ── Call / WhatsApp quick actions ─────────────────────────── */
    {
      id: 'call',
      keywords: ['call us', 'call now', 'ring', 'telephone'],
      responses: ["📞 You can call us directly at:\n+91 6301012437\n\nWe're available Monday–Saturday, 9 AM – 7 PM."],
      quickReplies: ['WhatsApp us', 'Location', 'Courses offered'],
    },
    {
      id: 'whatsapp',
      keywords: ['whatsapp us', 'whatsapp', 'wa', 'chat on whatsapp'],
      responses: ["💬 Click here to WhatsApp us directly:\nhttps://wa.me/916301012437\n\nOr open WhatsApp and message: +91 6301012437"],
      quickReplies: ['Call us', 'Location', 'Courses offered'],
    },

    /* ── Thanks / Bye ───────────────────────────────────────────── */
    {
      id: 'thanks',
      keywords: ['thanks', 'thank you', 'thankyou', 'ok', 'okay', 'got it', 'understood', 'great', 'nice', 'good', 'bye', 'goodbye', 'see you'],
      responses: [
        "You're welcome! 😊 Feel free to ask anything else. We're here to help!",
        "Happy to help! 🙌 All the best for your career with NEx-gEN!",
        "Glad I could help! 😊 Visit us anytime at Srikakulam. 🏫",
      ],
      quickReplies: ['Courses offered', 'Contact us'],
    },

  ], /* end intents */

}; /* end NEXGEN_BOT */
