export type Language = 'de' | 'en';
export type ChatRouteKey = 'home' | 'portfolio' | 'services' | 'about' | 'contact' | 'privacy' | 'legal' | 'terms';
export type ChatRoutes = Record<ChatRouteKey, string>;
export type ChatProject = {
  slug: string;
  title: Record<Language, string>;
  category: string;
  href: Record<Language, string>;
};
export type Reply = { topic: string; text: string; links: { label: string; href: string }[] };

export const routes: Record<Language, ChatRoutes> = {
  de: { home: '/', portfolio: '/portfolio', services: '/leistungen', about: '/ueber-mich', contact: '/kontakt', privacy: '/datenschutz', legal: '/impressum', terms: '/agb' },
  en: { home: '/en', portfolio: '/en/portfolio', services: '/en/services', about: '/en/about', contact: '/en/contact', privacy: '/en/privacy', legal: '/en/legal-notice', terms: '/en/terms' },
};
export const email = 'contact@madlenmedvedovskyy.de';
export const copy = {
  de: {
    open: 'Chat öffnen', close: 'Chat schließen', title: 'Madlen Assist',
    greeting: 'Hallo! Ich bin der automatische Website-Helfer. Ich helfe dir, Arbeiten, Leistungen und Informationen über Madlen zu finden.',
    input: 'Deine Frage eingeben …', send: 'Senden', history: 'Chatverlauf', emailLink: 'E-Mail schreiben',
    quick: ['Portfolio', 'Leistungen', 'Preise', 'Kontakt'], note: 'Keine Speicherung oder Weiterleitung deiner Nachrichten.',
    labels: { home: 'Zur Startseite', portfolio: 'Portfolio ansehen', services: 'Leistungen ansehen', about: 'Über Madlen', contact: 'Kontakt zu Madlen', privacy: 'Datenschutz', legal: 'Impressum', terms: 'AGB / Hinweise' },
    categories: { editorial: 'Editorial', events: 'Events', weddings: 'Hochzeiten', landscape: 'Landschaft' },
    replies: {
      pricing: 'Preise hängen vom Projekt ab. Madlen erstellt dein individuelles Angebot; bitte frage sie direkt.',
      availability: 'Bitte kläre deinen Wunschtermin direkt mit Madlen. Ich kann keine Verfügbarkeit prüfen oder Termine bestätigen.',
      services: 'Madlen bietet Portraits, Paare & Familien, Hochzeiten, Events, Videografie, Videoschnitt sowie Editorial & Commercial an.',
      portrait: 'Madlen fotografiert natürliche Portraits mit Licht, echten Orten und deiner Persönlichkeit.',
      family: 'Für Paare und Familien hält Madlen gemeinsame Momente ohne erzwungene Posen fest.',
      weddingService: 'Madlen begleitet Hochzeiten aufmerksam und hält die besonderen Momente vom Vorbereiten bis zum Tanz fest.',
      eventService: 'Madlen fotografiert Firmenfeiern, Kulturveranstaltungen und private Events.',
      video: 'Madlen bietet Videografie für Hochzeitsfilme, Event-Videos und kreative Kurzproduktionen an.',
      editing: 'Madlen übernimmt auch reine Videoschnitt-Projekte aus vorhandenem Rohmaterial, mit Gespür für Rhythmus und Atmosphäre.',
      commercial: 'Editorial & Commercial umfasst Fotografie für Magazine, Marken, Shops und kreative Projekte.',
      about: 'Madlens fotografischer Blick ist filmisch und editorial. Mode, Farbe, Bewegung und echte Begegnungen inspirieren ihre Arbeit. Ihre Perspektive wurde an der Hochschule der Medien geprägt und entwickelt sich an der Filmakademie Baden-Württemberg weiter.',
      contact: 'Auf der Kontaktseite findest du die Kontaktmöglichkeiten. Du kannst Madlen auch direkt per E-Mail schreiben.',
      email: 'Du erreichst Madlen unter dieser E-Mail-Adresse:',
      privacy: 'Informationen zum Datenschutz findest du hier:', legal: 'Das Impressum mit den rechtlichen Angaben findest du hier:', terms: 'Die AGB und Hinweise findest du hier:',
      portfolio: 'Entdecke Editorial, Events, Hochzeiten und Landschaft. Wähle eine Kategorie:',
      editorial: 'Hier findest du die editorialen Arbeiten im Portfolio.',
      events: 'Die Kategorie Events zeigt Veranstaltungsarbeiten. Sie kann auch für deine Suche nach Ausstellungen oder Firmenevents interessant sein.',
      weddings: 'Hier findest du Hochzeitsbilder und die zugehörigen Projektgalerien.',
      landscape: 'Hier findest du Landschaftsarbeiten, darunter Eibsee und Norwegen & Schweden.',
      project: 'Hier geht es direkt zur Projektgalerie:',
      home: 'Hier geht es zur Startseite.', thanks: 'Gern! Wenn du möchtest, zeige ich dir weitere Arbeiten oder Leistungen.',
      unclear: 'Was möchtest du finden: Portfolio, Leistungen, Preise oder Informationen über Madlen?',
      missing: 'Dazu finde ich keine passende Projektgalerie. Du kannst die vorhandenen Arbeiten im Portfolio ansehen oder eine Kategorie nennen.',
      unrelated: 'Ich helfe bei Madlens Arbeiten, Leistungen und der Navigation auf dieser Website. Zu anderen Themen kann ich keine Auskunft geben.',
    },
  },
  en: {
    open: 'Open chat', close: 'Close chat', title: 'Madlen Assist',
    greeting: 'Hello! I am the automated website helper. I can help you find Madlen’s work, services and information.',
    input: 'Type your question …', send: 'Send', history: 'Chat history', emailLink: 'Email Madlen',
    quick: ['Portfolio', 'Services', 'Pricing', 'Contact'], note: 'Your messages are neither stored nor forwarded.',
    labels: { home: 'Go to homepage', portfolio: 'View portfolio', services: 'View services', about: 'About Madlen', contact: 'Contact Madlen', privacy: 'Privacy policy', legal: 'Legal notice', terms: 'Terms & notes' },
    categories: { editorial: 'Editorial', events: 'Events', weddings: 'Weddings', landscape: 'Landscape' },
    replies: {
      pricing: 'Pricing depends on your project. Madlen prepares an individual quote; please ask her directly.',
      availability: 'Please check your preferred date directly with Madlen. I cannot check availability or confirm a booking.',
      services: 'Madlen offers portraits, couples & families, weddings, events, videography, video editing and Editorial & Commercial photography.',
      portrait: 'Madlen creates natural portraits shaped by light, real locations and your personality.',
      family: 'Madlen captures shared moments for couples and families without forced poses.',
      weddingService: 'Madlen photographs the special moments of your wedding, from preparations to dancing.',
      eventService: 'Madlen photographs corporate celebrations, cultural occasions and private events.',
      video: 'Madlen offers videography for wedding films, event videos and creative short productions.',
      editing: 'Madlen also edits existing footage, with attention to rhythm and atmosphere.',
      commercial: 'Editorial & Commercial includes photography for magazines, brands, shops and creative projects.',
      about: 'Madlen’s photographic perspective is cinematic and editorial, inspired by fashion, colour, movement and real encounters. Her perspective was shaped at Hochschule der Medien and continues to develop at Filmakademie Baden-Württemberg.',
      contact: 'You can find contact options on the contact page or email Madlen directly.',
      email: 'You can reach Madlen at this email address:',
      privacy: 'You can find privacy information here:', legal: 'You can find the legal notice here:', terms: 'You can find the terms and notes here:',
      portfolio: 'Explore Editorial, Events, Weddings and Landscape. Choose a category:',
      editorial: 'Explore the editorial work in the portfolio.',
      events: 'The Events category shows event work. It may also be useful when looking for exhibitions or corporate events.',
      weddings: 'Explore wedding photographs and their project galleries here.',
      landscape: 'Explore landscapes including Eibsee and Norway & Sweden.',
      project: 'Here is the direct link to the project gallery:',
      home: 'Here is the homepage.', thanks: 'You’re welcome! I can also show you more work or services.',
      unclear: 'What would you like to find: portfolio, services, pricing or information about Madlen?',
      missing: 'I could not find a matching project gallery. You can browse the existing portfolio or name a category.',
      unrelated: 'I help with Madlen’s work, services and navigation on this website. I cannot answer questions about other topics.',
    },
  },
};

export function normalize(value: string): string {
  return value.toLowerCase().replace(/ß/g, 'ss').normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/\b(und|and)\b|&/g, ' ').replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ')
    .replace(/\b(schutting|schuetting|shootng|shoting|shootig)\b/g, 'shooting')
    .replace(/\b(gallerie|galarie|gallary)\b/g, 'galerie');
}
const contains = (text: string, phrase: string) => (` ${text} `).includes(` ${normalize(phrase)} `);

// Ordered intent rules: specific topics first, generic navigation last.
export function answer(input: string, lang: Language, projects: ChatProject[], routeSet: ChatRoutes): Reply {
  const text = normalize(input);
  const ui = copy[lang];
  const link = (key: ChatRouteKey) => ({ label: ui.labels[key], href: routeSet[key] });
  const reply = (topic: keyof typeof ui.replies, links: Reply['links'] = []): Reply => ({ topic, text: ui.replies[topic], links });
  const contactLinks = () => [link('contact'), { label: ui.emailLink, href: `mailto:${email}` }];
  const has = (pattern: RegExp) => pattern.test(text);
  if (!text) return reply('unclear', [link('portfolio'), link('services'), link('about')]);
  // Off-site questions must not become bookings just because they mention "tomorrow".
  if (has(/\b(wetter|weather|politik|politics|bitcoin|horoskop|horoscope|rezept|recipe)\b/)) return reply('unrelated', [link('portfolio'), link('services')]);
  if (has(/\b(datenschutz|datenschutzerklarung|datenschutzerklaerung|privacy|cookies?)\b/)) return reply('privacy', [link('privacy')]);
  if (has(/\b(impressum|imprint|legal notice)\b/)) return reply('legal', [link('legal')]);
  if (has(/\b(agb|hinweise|terms|conditions)\b/)) return reply('terms', [link('terms')]);
  if (has(/\b(preis(e|en)?|kosten?|kostet|kost(en|et)los|pricing|prices?|costs?|how much|wie viel|wieviel|teuer|honorar|estimate|quotation|quote|angebot|angebote|budget|rabatt|discount|paket(e)?|packages?)\b/)) return reply('pricing', contactLinks());
  if (has(/\b(verfugbar|verfuegbar|verfugbarkeit|verfuegbarkeit|frei|freie|freien|termin(e)?|buchen|buchung|available|availability|dates?|book|booking|appointments?|zeit fur|zeit fuer|time for)\b/)) return reply('availability', contactLinks());
  const project = projects.find(p => [p.title.de, p.title.en, p.slug].some(name => contains(text, name)))
    ?? projects.find(p => p.category === 'landscape' && [p.title.de, p.title.en].some(name => normalize(name).split(' ').some(word => word.length > 3 && contains(text, word))));
  if (project) return reply('project', [{ label: project.title[lang], href: project.href[lang] }]);
  if (has(/\b(e mail|email|mail|emailadresse|mailadresse)\b/)) return reply('email', [{ label: email, href: `mailto:${email}` }, link('contact')]);
  if (has(/\b(kontakt|kontaktieren|contact|inquiry|anfrage|anschreiben|erreichen|reach)\b/)) return reply('contact', contactLinks());
  if (has(/\b(videoschnitt|video schnitt|videomontage|video editing|editing|schnitt|montage|rohmaterial|footage)\b/)) return reply('editing', [link('services')]);
  if (has(/\b(videografie|videographie|videography|videos?|films?|filme|hochzeitsfilm|wedding film)\b/)) return reply('video', [link('services')]);
  if (has(/\b(portraits?|portrats?|portraets?|portraitshooting|portraitfotografie|portraitfotographie)\b/)) return reply('portrait', [link('services')]);
  if (has(/\b(paare?|familie(n)?|familienfotografie|couples?|families|family)\b/)) return reply('family', [link('services')]);
  if (has(/\b(commercial|werbung|werbefotografie|produktfotografie|brand|brands|produkt(e)?|product|products)\b/)) return reply('commercial', [link('services')]);
  const category = has(/\b(hochzeit(en|sfotos|sbilder|sfotografie|sfotograf|sfotografin)?|hochzeitsgalerie|weddings?|bridal|heiraten)\b/) ? 'weddings'
    : has(/\b(events?|veranstaltung(en)?|ausstellung(en)?|exhibitions?|corporate|firmenfeier(n)?|firmenevents?)\b/) ? 'events'
    : has(/\b(editorial|editoriale|mode|fashion)\b/) ? 'editorial'
    : has(/\b(landschaft(en|sfotos|sbilder|sfotografie)?|landscapes?|nature|natur)\b/) ? 'landscape' : null;
  if (category) {
    const serviceIntent = has(/\b(bietet|bieten|offer|offers|services?|leistungen?)\b/) && !has(/\b(bilder|fotos|photos|pictures|portfolio|galerie|gallery)\b/);
    if (serviceIntent && category !== 'landscape') return reply(category === 'weddings' ? 'weddingService' : category === 'events' ? 'eventService' : 'commercial', [link('services')]);
    return reply(category, [{ label: ui.categories[category], href: `${routeSet.portfolio}?category=${category}` }]);
  }
  if (has(/\b(uber mich|ueber mich|uber madlen|ueber madlen|about madlen|about you|biografie|biography|wer ist madlen|who is madlen)\b/)) return reply('about', [link('about')]);
  if (has(/\b(leistungen?|services?|shooting|fotoshooting|photography|fotografie)\b/)) return reply('services', [link('services')]);
  if (has(/\b(portfolio|arbeiten|work|galerien|galleries)\b/) || /^(galerie|gallery|bilder|fotos|photos|pictures)$/.test(text)) return reply('portfolio', Object.entries(ui.categories).map(([category, label]) => ({label, href: `${routeSet.portfolio}?category=${category}`})));
  if (has(/\b(projekt|project|galerie|gallery|bilder|fotos|photos|pictures)\b/)) return reply('missing', [link('portfolio')]);
  if (has(/\b(startseite|homepage|home page)\b/)) return reply('home', [link('home')]);
  if (/^(hallo|hi|hello|hey|guten tag|good morning)( madlen)?$/.test(text)) return {topic:'greeting',text:ui.greeting,links:[]};
  if (/^(danke|dankeschon|dankeschoen|vielen dank|thanks|thank you)( sehr| so much)?$/.test(text)) return reply('thanks');
  if (/^(hilfe|help|fragen|questions|was|what|huh|hm|ich weiss nicht|i don t know|was kannst du|what can you do|kannst du mir helfen|can you help me)$/.test(text)) return reply('unclear', [link('portfolio'), link('services'), link('about')]);
  return reply('unrelated', [link('portfolio'), link('services')]);
}
