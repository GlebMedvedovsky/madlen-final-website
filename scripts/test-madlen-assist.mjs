import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { answer, copy, normalize, routes } from '../src/lib/madlen-assist.ts';
import { projects } from '../src/data/projects.ts';

const chatProjects = projects.map(project => ({
 ...project,
 href: {
  de: `${routes.de.portfolio}/${project.slug}`,
  en: `${routes.en.portfolio}/${project.slug}`,
 },
}));

const cases = [
 ['ich kann nicht finden Hochzeit Galerie', 'weddings'],
 ['wo ich finde Datenschutz?', 'privacy'],
 ['was kostet ein Schütting?', 'pricing'],
 ['Zeig mir die Bilder von Laura und Micha.', 'project', 'laura-micha'],
 ['Wo finde ich Events oder Ausstellungen?', 'events'],
 ['Ich suche Landschaftsfotos aus Norwegen.', 'project', 'norway-sweden'],
 ['Bietet Madlen auch Videoschnitt an?', 'editing'],
 ['Was kostet Hochzeitsfotografie?', 'pricing'],
 ['Wo finde ich das Impressum?', 'legal'],
 ['Wie wird morgen das Wetter?', 'unrelated'],
 ['Where can I see wedding photos?', 'weddings'],
 ['Show me Security Policy Dialogue.', 'project', 'sicherheitspolitischer-dialog'],
 ['Do you offer video editing?', 'editing'],
 ['How much does a wedding shoot cost?', 'pricing'],
 ['Where is your privacy policy?', 'privacy'],
 ['I cannot find the wedding gallery', 'weddings'],
 ['Show me pictures of Laura and Micha', 'project', 'laura-micha'],
 ['Where can I find events or exhibitions?', 'events'],
 ['I am looking for landscape photos from Norway', 'project', 'norway-sweden'],
 ['Where is the legal notice?', 'legal'],
 ['What will the weather be like tomorrow?', 'unrelated'],
 ['Is Madlen available for a wedding?', 'availability'],
 ['Ist ein Termin für Hochzeit frei?', 'availability'],
 ['  WAS   KOSTET ein SCHÜTTING?! ', 'pricing'],
 ['Hochzeit Gallerie', 'weddings'],
 ['Wie teuer ist eine Hochzeit?', 'pricing'], ['Wie viel für ein Shooting?', 'pricing'], ['Schütting', 'services'], ['What can you do?', 'unclear'],
 ['PORTRÄTS', 'portrait'], ['Portraets', 'portrait'],
 ['Paare & Familien', 'family'], ['Do you offer weddings?', 'weddingService'],
 ['Does Madlen offer corporate events?', 'eventService'],
 ['Videografie', 'video'], ['Videoschnitt', 'editing'], ['Editorial & Commercial', 'commercial'],
 ['Wer ist Madlen?', 'about'], ['About Madlen', 'about'], ['AGB', 'terms'], ['terms and conditions', 'terms'],
 ['Email?', 'email'], ['Danke!', 'thanks'], ['Thank you!', 'thanks'], ['Hallo', 'greeting'], ['Hello!', 'greeting'],
 ['?', 'unclear'], ['   ', 'unclear'], ['Show me the Mars gallery', 'missing'],
 ['<img src=x onerror=alert(1)>', 'unrelated'], ['workout', 'unrelated'], ['costume', 'unrelated'],
];
for (const lang of ['de', 'en']) {
 test(`${lang}: bilingual intent cases, priority and trusted links`, () => {
  for (const [input, topic, slug] of cases) {
   const result = answer(input, lang, chatProjects, routes[lang]);
   assert.equal(result.topic, topic, input);
   if (topic !== 'greeting') assert.equal(result.text, copy[lang].replies[topic]);
   if (slug) assert.equal(result.links[0].href, `${routes[lang].portfolio}/${slug}`);
   for (const link of result.links) {
    assert.ok(link.href.startsWith('mailto:') || (lang === 'en' ? link.href.startsWith('/en') : !link.href.startsWith('/en')), input);
    assert.ok(!link.href.includes('<'));
   }
   if(topic==='pricing' || topic==='availability') assert.equal(result.links[0].href, routes[lang].contact);
  }
 });
 test(`${lang}: every real project and name variant has a built route`, () => {
  assert.equal(projects.length,16);
  for (const project of projects) {
   for (const title of [project.slug, project.title.de, project.title.en, project.title.de.replace('&','und'), project.title.en.replace('&','and')]) {
    const result=answer(`Show me ${title}`,lang,chatProjects,routes[lang]);
    assert.equal(result.links[0].href,`${routes[lang].portfolio}/${project.slug}`,title);
    assert.ok(existsSync(`dist${result.links[0].href}/index.html`));
   }
  }
 });
 test(`${lang}: categories, routes and quick actions`, () => {
  for (const category of ['weddings','events','editorial','landscape']) assert.equal(answer(category,lang,chatProjects,routes[lang]).links[0].href,`${routes[lang].portfolio}?category=${category}`);
  for (const route of Object.values(routes[lang])) assert.ok(existsSync(`dist${route==='/'?'':route}/index.html`),route);
  for (const label of copy[lang].quick) assert.ok(answer(label,lang,chatProjects,routes[lang]).links.length);
 });
}
test('normalization preserves word boundaries and names',()=>{
 assert.equal(normalize('Laura & Micha'),normalize('Laura und Micha'));
 assert.equal(normalize('Norwegen & Schweden'),normalize('Norwegen and Schweden'));
});
