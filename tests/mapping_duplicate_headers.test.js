'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(process.argv[2] || path.join(__dirname, '..', 'assets', 'mapping.js'), 'utf8');
const selectedFunction = source.slice(source.indexOf('function selectedMappingColumn()'), source.indexOf('let pendingMappingSaves'));
const handlersSource = source.slice(source.indexOf("$(document).on('click', '#btn-remove-correspondance'"), source.indexOf("$(document).on('change', '#mapping'"));
assert.ok(selectedFunction.includes('data(\'column-index\')'));
assert.ok(handlersSource.includes('#btn-save-mapping-champ-array'));

const handlers = {};
const cells = [0, 1].map(index => ({data: {index, 'lib-colonne': 'Code', 'code-mapping': 'old-' + index}, classes: new Set()}));
cells.forEach(item => { item.attrs = {'data-lib-colonne': 'Code'}; });
const modal = {data: {'column-index': 0}};
const elements = {'#mapping': {value: '7', data: {url: '/save'}}, '#btnModeAvance': {}, '#champs': {value: 'new-code'}, '#format_date': {value: 'Y-m-d'}, '#delimiteur': {value: ';'}, '#modalSelectChamps': modal};
let saves = 0;
const document = {};

function wrap(items) {
  return {
    length: items.length,
    filter(fn) { return wrap(items.filter((item, index) => fn.call(item, index, item))); },
    each(fn) { items.forEach((item, index) => fn.call(item, index, item)); return this; },
    attr(key) { return items[0]?.attrs?.[key]; },
    prop() { return this; },
    data(key, value) {
      if (arguments.length === 1) return items[0]?.data?.[key];
      items.forEach(item => { item.data[key] = value; }); return this;
    },
    removeData(key) { items.forEach(item => delete item.data[key]); return this; },
    val() { return items[0]?.value; },
    text(value) { items.forEach(item => { item.text = value; }); return this; },
    addClass(value) { items.forEach(item => item.classes.add(value)); return this; },
    removeClass(value) { items.forEach(item => item.classes.delete(value)); return this; },
    modal() { return this; },
  };
}
function $(selector) {
  if (selector === document) return {on(event, name, handler) { handlers[name] = handler; }};
  if (selector === '#tr_mapping .mapping_data_value') return wrap(cells);
  if (typeof selector === 'object') return wrap([selector]);
  return wrap(elements[selector] ? [elements[selector]] : []);
}
vm.runInNewContext(selectedFunction + handlersSource, {document, $, Number, saveMapping: () => saves++});

for (const [handler, expectedType] of [['#btn-valid-champ', undefined], ['#btn-save-mapping-champ-date', 'Y-m-d'], ['#btn-save-mapping-champ-array', ';']]) {
  cells[0].data['code-mapping'] = 'old-0';
  cells[1].data['code-mapping'] = 'old-1';
  modal.data['column-index'] = 1;
  handlers[handler].call({});
  assert.equal(cells[0].data['code-mapping'], 'old-0', handler + ' doit préserver la première colonne');
  assert.equal(cells[1].data['code-mapping'], 'new-code');
  assert.equal(cells[1].data['type-mapping'], expectedType);
}
modal.data['column-index'] = 0;
handlers['#btn-remove-correspondance'].call({});
assert.equal(cells[0].data['code-mapping'], '');
assert.equal(cells[1].data['code-mapping'], 'new-code');
assert.equal(saves, 4);
modal.data['column-index'] = undefined;
handlers['#btn-valid-champ'].call({});
assert.equal(saves, 4, 'une colonne absente ne doit pas déclencher de sauvegarde');
const saveFunction = source.slice(source.indexOf('function saveMapping()'), source.indexOf('function loadMappingConfigurations()'));
let payload;
$.ajax = options => { payload = options.data; options.success(); options.complete(); };
const saveContext = {document, $, Number, alert: () => {}, updateRecapitulatif: () => {}, loadConfiguration: () => {}};
vm.runInNewContext('let pendingMappingSaves = 0; ' + saveFunction + '; saveMapping();', saveContext);
assert.equal(payload.mapping.length, 2);
assert.equal(payload.mapping[0].index, 0);
assert.equal(payload.mapping[1].index, 1);
assert.equal(payload.mapping[0].nom_entete, 'Code');
assert.equal(payload.mapping[1].nom_entete, 'Code');
assert.equal(payload.mapping[0].mapping_code, '');
assert.equal(payload.mapping[1].mapping_code, 'new-code');
cells[0].attrs['data-lib-colonne'] = '001';
saveContext.saveMapping();
assert.equal(payload.mapping[0].nom_entete, '001', 'le texte exact de l’en-tête doit être conservé');
console.log('Correspondances et sauvegarde : colonnes de même nom indépendantes, en-têtes préservés.');
