<script setup>
import { onMounted, onBeforeUnmount, ref, defineExpose } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholders: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const wrapper = ref(null);
let gjsEditor = null;


// ---- Toolbar controls — live entirely outside GrapesJS ----
const marginTop        = ref(40);
const marginRight      = ref(55);
const marginBottom     = ref(40);
const marginLeft       = ref(55);
const globalFontFamily = ref('DejaVu Sans, Arial, sans-serif');
const globalFontSize   = ref(11);   // pt

const FONT_OPTIONS = [
    { label: 'Arial (DejaVu Sans)',  value: 'DejaVu Sans, Arial, sans-serif' },
    { label: 'Times New Roman',      value: "'Times New Roman', Times, serif" },
    { label: 'Courier New',          value: "'Courier New', Courier, monospace" },
    { label: 'Georgia',              value: 'Georgia, serif' },
];

// Non-body, non-page CSS from the original template (e.g. .footer, .letter-body p)
let originalCss = '';

// ---- Text alignment for selected element ----
const currentAlign = ref('');
let lastSelected = null;   // cached — click deselects before setAlign runs
const alignMap   = new Map(); // elementId → align value — persists across makeFullHtml() calls

const setAlign = (value) => {
    if (!gjsEditor) return;
    const sel = lastSelected || gjsEditor.getSelected();
    if (!sel) return;
    const id = sel.getId();
    alignMap.set(id, value);                      // persisted in our own map
    sel.setStyle({ ...sel.getStyle(), 'text-align': value }); // visual only
    currentAlign.value = value;
    emit('update:modelValue', makeFullHtml());
};

const onControlChange = () => {
    updateCanvasMargins();
    emit('update:modelValue', makeFullHtml());
};

// Inject margin preview into the canvas iframe so the preview matches the PDF
const updateCanvasMargins = () => {
    if (!gjsEditor) return;
    try {
        const canvasDoc = gjsEditor.Canvas.getDocument();
        if (!canvasDoc) return;
        let el = canvasDoc.getElementById('tpl-margins-preview');
        if (!el) {
            el = canvasDoc.createElement('style');
            el.id = 'tpl-margins-preview';
            (canvasDoc.head || canvasDoc.documentElement).appendChild(el);
        }
        el.textContent = `body { padding: ${marginTop.value}px ${marginRight.value}px ${marginBottom.value}px ${marginLeft.value}px !important; box-sizing: border-box !important; }`;
    } catch(e) {}
};

// ---- Letter blocks for drag-and-drop panel ----
const buildBlocks = (placeholders) => {
    const letterBlocks = [
        {
            id: 'company-header',
            label: 'Company Header',
            category: { id: 'letter', label: 'Letter Blocks', open: true },
            content: `<table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:2px solid #1e3a5f;padding-bottom:16px;margin-bottom:24px;">
  <tr>
    <td style="vertical-align:middle;width:50%;">{{company_logo}}</td>
    <td align="right" style="vertical-align:middle;font-size:0.9em;color:#555;line-height:1.7;">
      <strong style="font-size:1.2em;color:#1e3a5f;display:block;">{{company_name}}</strong>
      {{company_address}}, {{company_city}}<br>
      {{company_phone}} | {{company_email}}
    </td>
  </tr>
</table>`,
        },
        {
            id: 'letter-date',
            label: 'Date Line',
            category: { id: 'letter', label: 'Letter Blocks', open: true },
            content: `<p style="text-align:right;color:#555;margin-bottom:20px;">{{date}}</p>`,
        },
        {
            id: 'recipient-block',
            label: 'Recipient Address',
            category: { id: 'letter', label: 'Letter Blocks', open: true },
            content: `<div style="margin-bottom:24px;line-height:1.7;">
  <strong>{{recipient_name}}</strong><br>
  {{recipient_address}}
</div>`,
        },
        {
            id: 'subject-ref',
            label: 'Subject &amp; Reference',
            category: { id: 'letter', label: 'Letter Blocks', open: true },
            content: `<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
  <tr>
    <td width="100" style="font-weight:bold;color:#444;padding-bottom:4px;">Subject:</td>
    <td style="font-weight:bold;padding-bottom:4px;">{{subject}}</td>
  </tr>
  <tr>
    <td style="font-weight:bold;color:#444;">Reference:</td>
    <td>{{reference}}</td>
  </tr>
</table>`,
        },
        {
            id: 'letter-body',
            label: 'Letter Body',
            category: { id: 'letter', label: 'Letter Blocks', open: true },
            content: `<div style="line-height:1.8;margin-bottom:24px;">{{body}}</div>`,
        },
        {
            id: 'closing-block',
            label: 'Closing &amp; Signature',
            category: { id: 'letter', label: 'Letter Blocks', open: true },
            content: `<div style="margin-top:32px;line-height:1.8;">
  <p>{{closing}}</p>
  <br><br>
  <p><strong>{{sender_name}}</strong></p>
  <p style="color:#666;">{{sender_title}}</p>
</div>`,
        },
        {
            id: 'divider',
            label: 'Divider',
            category: { id: 'layout', label: 'Layout', open: false },
            content: `<hr style="border:none;border-top:1px solid #ddd;margin:16px 0;" />`,
        },
        {
            id: 'spacer',
            label: 'Spacer',
            category: { id: 'layout', label: 'Layout', open: false },
            content: `<div style="height:32px;font-size:0;line-height:0;">&nbsp;</div>`,
        },
        {
            id: 'text-para',
            label: 'Text Paragraph',
            category: { id: 'content', label: 'Content', open: false },
            content: `<p style="line-height:1.7;margin-bottom:12px;color:#333;">Click to edit this text...</p>`,
        },
        {
            id: 'two-columns',
            label: 'Two Columns',
            category: { id: 'layout', label: 'Layout', open: false },
            content: `<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
  <tr>
    <td width="48%" style="vertical-align:top;padding-right:8px;">Left column content here</td>
    <td width="4%">&nbsp;</td>
    <td width="48%" style="vertical-align:top;padding-left:8px;">Right column content here</td>
  </tr>
</table>`,
        },
    ];

    const phBlocks = Object.entries(placeholders).map(([key, desc]) => ({
        id: `ph-${key.replace(/[{}]/g, '')}`,
        label: desc,
        category: { id: 'placeholders', label: 'Placeholders', open: false },
        content: key,
    }));

    return [...letterBlocks, ...phBlocks];
};

// ---- Build full HTML for saving ----
const makeFullHtml = () => {
    if (!gjsEditor) return '';

    // Strip any <body> wrapper GrapesJS adds to getHtml() output
    let innerHtml = gjsEditor.getHtml();
    innerHtml = innerHtml.replace(/<body[^>]*>/gi, '').replace(/<\/body>/gi, '');

    // Apply our alignment map as inline styles on the elements.
    // alignMap is the single source of truth — it survives every makeFullHtml() call
    // and is never affected by GrapesJS's CSS engine.
    if (alignMap.size > 0) {
        const parser = new DOMParser();
        const tmpDoc = parser.parseFromString('<body>' + innerHtml + '</body>', 'text/html');
        for (const [id, align] of alignMap) {
            const el = tmpDoc.getElementById(id);
            if (el) {
                const existing = (el.getAttribute('style') || '')
                    .replace(/text-align\s*:[^;]+;?\s*/gi, '').trim().replace(/;$/, '');
                el.setAttribute('style', (existing ? existing + '; ' : '') + `text-align: ${align}`);
            }
        }
        innerHtml = tmpDoc.body.innerHTML;
    }

    const padding = `${marginTop.value}px ${marginRight.value}px ${marginBottom.value}px ${marginLeft.value}px`;

    return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style id="tpl-base">
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: ${globalFontFamily.value}; font-size: ${globalFontSize.value}pt; color: #333; line-height: 1.6; }
${originalCss}
</style>
</head>
<body>
<div class="page" style="padding: ${padding};">
${innerHtml}
</div>
</body>
</html>`;
};

// ---- Load HTML into GrapesJS ----
const loadHtml = (fullHtml) => {
    if (!fullHtml || !gjsEditor) return;
    const parser = new DOMParser();
    const doc    = parser.parseFromString(fullHtml, 'text/html');

    // Read padding into margin controls
    const pageDiv = doc.querySelector('.page');
    if (pageDiv) {
        const m = (pageDiv.getAttribute('style') || '')
            .match(/padding:\s*([\d.]+)px\s+([\d.]+)px(?:\s+([\d.]+)px\s+([\d.]+)px)?/);
        if (m) {
            marginTop.value    = parseFloat(m[1]);
            marginRight.value  = parseFloat(m[2]);
            marginBottom.value = m[3] ? parseFloat(m[3]) : parseFloat(m[1]);
            marginLeft.value   = m[4] ? parseFloat(m[4]) : parseFloat(m[2]);
        }
    }

    // Read font controls from body rule in tpl-base
    const allCss = Array.from(doc.querySelectorAll('head style')).map(s => s.textContent).join('\n');
    const ffMatch = allCss.match(/body\s*\{[^}]*font-family:\s*([^;}\n]+)/);
    const fsMatch = allCss.match(/body\s*\{[^}]*font-size:\s*([\d.]+)pt/);
    if (ffMatch) globalFontFamily.value = ffMatch[1].trim();
    if (fsMatch) globalFontSize.value   = parseFloat(fsMatch[1]);

    // Store template-specific CSS, stripping rules we generate ourselves
    const baseEl = doc.querySelector('style#tpl-base') || null;
    const rawCss = baseEl ? baseEl.textContent : allCss;
    originalCss = rawCss
        .replace(/\*\s*\{[^}]*\}/g, '')
        .replace(/body\s*\{[^}]*\}/g, '')
        .replace(/\.page\s*\{[^}]*\}/g, '')
        .trim();

    // Load only inner content — also strip stale <body> tags saved from previous versions
    const innerHtml = (pageDiv ? pageDiv.innerHTML : doc.body.innerHTML)
        .replace(/<body[^>]*>/gi, '')
        .replace(/<\/body>/gi, '');

    // Restore alignMap from inline styles already baked into saved HTML
    alignMap.clear();
    const tmpDoc2 = new DOMParser().parseFromString('<body>' + innerHtml + '</body>', 'text/html');
    tmpDoc2.body.querySelectorAll('[id][style*="text-align"]').forEach(el => {
        const m = el.getAttribute('style').match(/text-align\s*:\s*([^;]+)/i);
        if (m) alignMap.set(el.id, m[1].trim());
    });

    gjsEditor.setComponents(innerHtml || '');

    // Reset wrapper so GrapesJS never adds its own margin/padding
    const root = gjsEditor.getWrapper();
    if (root) root.setStyle({ margin: '0', padding: '0' });

    // Show margin in the canvas preview
    updateCanvasMargins();
};

onMounted(async () => {
    const grapesjs = (await import('grapesjs')).default;
    await import('grapesjs/dist/css/grapes.min.css');

    gjsEditor = grapesjs.init({
        container: wrapper.value,
        fromElement: false,
        storageManager: false,
        height: '680px',
        width: '100%',

        deviceManager: {
            devices: [{ id: 'a4', name: 'A4 (794px)', width: '794px', widthMedia: '' }],
        },

        blockManager: {
            blocks: buildBlocks(props.placeholders),
        },

        // Style manager disabled — GrapesJS style panel converts inline styles to
        // ID-based CSS rules that break DomPDF layout.
        // avoidInlineStyle:false ensures setStyle() keeps styles as inline attributes
        // (not ID-based CSS rules), so getHtml() serialises them correctly.
        styleManager: { sectors: [] },
        avoidInlineStyle: false,

        canvas: { styles: [] },
    });

    gjsEditor.runCommand('open-blocks');

    loadHtml(props.modelValue);

    gjsEditor.on('update', () => {
        emit('update:modelValue', makeFullHtml());
    });

    gjsEditor.on('component:selected', (component) => {
        lastSelected = component;
        // Read from alignMap first (our source of truth), fall back to GrapesJS style
        currentAlign.value = alignMap.get(component.getId()) || (component.getStyle()['text-align'] || '').trim();
    });

    gjsEditor.on('component:deselected', () => {
        // Don't clear lastSelected — toolbar click deselects before setAlign fires
        currentAlign.value = '';
    });
});

onBeforeUnmount(() => {
    if (gjsEditor) {
        gjsEditor.destroy();
        gjsEditor = null;
    }
});

// Expose so parent can pull latest HTML at save time
defineExpose({ getHtml: () => makeFullHtml() });
</script>

<template>
    <div class="grapes-editor-container" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
        <!-- Toolbar: font + margin controls (fully outside GrapesJS control) -->
        <div style="display:flex;align-items:center;gap:14px;padding:8px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:12px;color:#555;flex-wrap:wrap;">
            <span style="font-weight:600;color:#374151;">Font</span>
            <select v-model="globalFontFamily" @change="onControlChange"
                style="padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;">
                <option v-for="opt in FONT_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <label style="display:flex;align-items:center;gap:4px;">
                Size&nbsp;(pt)
                <input type="number" v-model.number="globalFontSize" @change="onControlChange" min="6" max="24"
                    style="width:52px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;" />
            </label>
            <span style="color:#d1d5db;">|</span>
            <span style="font-weight:600;color:#374151;">Margins&nbsp;(px)</span>
            <label style="display:flex;align-items:center;gap:3px;">Top
                <input type="number" v-model.number="marginTop" @input="onControlChange" @change="onControlChange" min="0" max="200"
                    style="width:52px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;margin-left:3px;" />
            </label>
            <label style="display:flex;align-items:center;gap:3px;">Right
                <input type="number" v-model.number="marginRight" @input="onControlChange" @change="onControlChange" min="0" max="200"
                    style="width:52px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;margin-left:3px;" />
            </label>
            <label style="display:flex;align-items:center;gap:3px;">Bottom
                <input type="number" v-model.number="marginBottom" @input="onControlChange" @change="onControlChange" min="0" max="200"
                    style="width:52px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;margin-left:3px;" />
            </label>
            <label style="display:flex;align-items:center;gap:3px;">Left
                <input type="number" v-model.number="marginLeft" @input="onControlChange" @change="onControlChange" min="0" max="200"
                    style="width:52px;padding:2px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;margin-left:3px;" />
            </label>
            <span style="color:#d1d5db;">|</span>
            <span style="font-weight:600;color:#374151;">Align</span>
            <div style="display:flex;gap:3px;">
                <button v-for="align in ['left','center','right','justify']" :key="align"
                    type="button"
                    @mousedown.prevent="setAlign(align)"
                    :title="'Align ' + align"
                    :style="{
                        padding: '3px 8px',
                        border: '1px solid',
                        borderColor: currentAlign === align ? '#1e3a5f' : '#d1d5db',
                        borderRadius: '4px',
                        background: currentAlign === align ? '#1e3a5f' : '#fff',
                        color: currentAlign === align ? '#fff' : '#555',
                        cursor: 'pointer',
                        fontSize: '12px',
                    }">
                    <span v-if="align === 'left'">&#8676;</span>
                    <span v-else-if="align === 'center'">&#8596;</span>
                    <span v-else-if="align === 'right'">&#8677;</span>
                    <span v-else>&#8644;</span>
                </button>
            </div>
        </div>
        <div ref="wrapper"></div>
    </div>
</template>

<style>
.grapes-editor-container .gjs-block {
    width: auto;
    min-height: auto;
}
.grapes-editor-container .gjs-cv-canvas {
    background: #f3f4f6;
}
.grapes-editor-container .gjs-frame-wrapper {
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
}
</style>
