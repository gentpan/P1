// Read per-page values without separate inline JavaScript resources.
const p1RuntimeConfig = (() => {
	try {
		if (document.body?.dataset.p1Runtime) return JSON.parse(document.body.dataset.p1Runtime);
		// Keep previously cached pages functional during an asset rollout.
		return { views: window.p1ViewsConfig, likes: window.p1LikesConfig, pjax: window.p1PjaxConfig };
	}
	catch { return {}; }
})();

// Public IP lookup runs in the visitor's browser, independently of the site's CDN.
(() => {
	'use strict';
	const cacheKey = 'p1-public-ip-mypublicipnow-v2';
	let cached = { ip: '', expires: 0 };
	let pending;
	const valid = (value) => {
		if (typeof value !== 'string' || value.length > 45) return false;
		if (value.includes(':')) {
			if (!/^[0-9a-f:.]+$/i.test(value)) return false;
			try { new URL(`http://[${value}]/`); return true; } catch { return false; }
		}
		return /^\d{1,3}(?:\.\d{1,3}){3}$/.test(value) && value.split('.').every((part) => Number(part) <= 255);
	};
	try {
		const saved = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
		if (saved && valid(saved.ip) && Number(saved.expires) > Date.now() && Number(saved.expires) <= Date.now() + 300000) cached = saved;
	} catch { /* Storage is optional, including in private browsing. */ }
	function fillForm(form, ip) {
		if (!(form instanceof HTMLFormElement) || !valid(ip)) return;
		let field = form.querySelector('[name="p1_public_ip"]');
		if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'p1_public_ip'; form.append(field); }
		field.value = ip;
	}
	async function lookup() {
		const controller = new AbortController();
		const timer = setTimeout(() => controller.abort(), 3000);
		try {
			// The browser chooses IPv4/IPv6; following redirects matches curl -L.
			const response = await fetch('https://mypublicipnow.com/', {
				mode: 'cors', credentials: 'omit', cache: 'no-store', redirect: 'follow',
				referrerPolicy: 'no-referrer', signal: controller.signal,
			});
			if (!response.ok) return '';
			const body = await response.text();
			if (body.length > 4096) return '';
			const ip = body.trim();
			return valid(ip) ? ip : '';
		} catch { return ''; } // An unavailable lookup never blocks a comment submission.
		finally { clearTimeout(timer); }
	}
	function get() {
		if (cached.expires > Date.now()) return Promise.resolve(cached.ip);
		if (pending) return pending;
		pending = lookup().then((ip) => {
			cached = { ip, expires: Date.now() + (ip ? 300000 : 30000) };
			if (ip) {
				try { sessionStorage.setItem(cacheKey, JSON.stringify(cached)); } catch { /* Optional cache. */ }
				document.querySelectorAll('#commentform').forEach((form) => fillForm(form, ip));
			}
			return ip;
		}).finally(() => { pending = null; });
		return pending;
	}
	window.p1PublicIP = { get, fillForm };
	const mount = () => { void get().then((ip) => document.querySelectorAll('#commentform').forEach((form) => fillForm(form, ip))); };
	document.addEventListener('p1:page-ready', mount);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
	else mount();
})();

// ================================================================
// toast
// ================================================================
(() => {
	'use strict';

	const stack = document.getElementById('p1-toast-stack');
	if (!stack) return;

	const icons = {
		success: '<path d="m5 12 4 4L19 6"/>',
		comment: '<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.6A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8.5 11.5h7"/>',
		copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
		like: '<path d="M20.8 8.6c0 4.2-8.8 10.2-8.8 10.2S3.2 12.8 3.2 8.6a4.5 4.5 0 0 1 8.8-1.2 4.5 4.5 0 0 1 8.8 1.2Z"/>',
		celebrate: '<path d="m5 19 3.5-12 8.5 8.5L5 19Z"/><path d="m14 4 .5-2M19 8l2-1M18 3l2-1M10 3l1 2M17 11l3 1"/>',
		error: '<path d="M12 8v5M12 16h.01"/><circle cx="12" cy="12" r="9"/>',
	};

	function dismiss(item) {
		if (!item.isConnected || item.classList.contains('is-leaving')) return;
		window.clearTimeout(item.dismissTimer);
		item.classList.add('is-leaving');
		window.setTimeout(() => item.remove(), 180);
	}

	function show(title, options = {}) {
		const type = Object.prototype.hasOwnProperty.call(icons, options.type) ? options.type : 'success';
		const item = document.createElement('div');
		item.className = `p1-toast p1-toast--${type}`;
		if (type === 'error') item.setAttribute('role', 'alert');

		const icon = document.createElement('span');
		icon.className = 'p1-toast__icon';
		icon.setAttribute('aria-hidden', 'true');
		icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">${icons[type]}</svg>`;

		const content = document.createElement('span');
		content.className = 'p1-toast__content';
		const heading = document.createElement('strong');
		heading.textContent = String(title);
		content.append(heading);
		if (options.detail && stack.dataset.style !== 'capsule') {
			const detail = document.createElement('small');
			detail.textContent = String(options.detail);
			content.append(detail);
		}

		item.append(icon, content);
		stack.prepend(item);
		while (stack.children.length > 3) {
			const oldest = stack.lastElementChild;
			window.clearTimeout(oldest.dismissTimer);
			oldest.remove();
		}
		item.dismissTimer = window.setTimeout(() => dismiss(item), Math.max(1500, Number(options.duration) || 4200));
		return item;
	}

	window.p1Toast = { show };
})();
;

// ================================================================
// post-card-images
// ================================================================
(function () {
	'use strict';

	function prepareImages(root) {
		root.querySelectorAll('.post-card-image img').forEach((image) => {
			if (image.dataset.p1ImagePrepared) {
				return;
			}
			image.dataset.p1ImagePrepared = '1';

			// A cached image is already visible; hiding it here causes a flash.
			if (image.complete) {
				return;
			}

			image.classList.add('is-loading');
			let revealed = false;
			const reveal = () => {
				if (revealed) {
					return;
				}
				revealed = true;
				const show = () => window.requestAnimationFrame(() => {
					window.requestAnimationFrame(() => image.classList.add('is-loaded'));
				});

				if (image.naturalWidth && typeof image.decode === 'function') {
					image.decode().then(show, show);
				} else {
					show();
				}
			};

			image.addEventListener('load', reveal, { once: true });
			image.addEventListener('error', reveal, { once: true });
			if (image.complete) {
				reveal();
			}
		});
	}

	prepareImages(document);
	document.addEventListener('p1:posts-updated', (event) => prepareImages(event.detail.root));
	document.addEventListener('p1:page-ready', (event) => prepareImages(event.detail.root));
})();
;

// ================================================================
// comments
// ================================================================
(() => {
  'use strict';

  let controller;
  let geoObserver;

  function mount() {
    controller?.abort();
    geoObserver?.disconnect();
    controller = new AbortController();
    const signal = controller.signal;
    const root = document.querySelector('.p1-conversations');
    if (!root) return;

    const bar = root.querySelector('[data-p1-c-emojis]');
    if (bar) bar.hidden = false;
    window.addComment?.init();

    const queue = [];
    let running = 0;
    const queued = new WeakSet();
    function pump() {
      while (running < 3 && queue.length && !signal.aborted) {
        const node = queue.shift();
        running++;
        fetch(node.dataset.p1CommentLocation, { signal, credentials: 'same-origin' })
          .then(response => {
            if (!response.ok) throw new Error('Location unavailable');
            return response.json();
          })
          .then(data => {
            if (!node.isConnected || signal.aborted || typeof data.label !== 'string' || !data.label) return;
            node.textContent = data.label;
            if (/^[a-z]{2}$/.test(data.code || '')) {
              const flag = document.createElement('img');
              flag.className = 'p1-c-location-flag';
              flag.src = `https://flagcdn.io/flags/4x3/${data.code}.svg`;
              flag.alt = '';
              flag.width = 19;
              flag.height = 14;
              flag.addEventListener('error', () => flag.remove(), { once: true });
              node.prepend(flag);
            }
            node.hidden = false;
          })
          .catch(() => {})
          .finally(() => { running--; pump(); });
      }
    }
    function enqueue(node) {
      if (queued.has(node)) return;
      queued.add(node);
      queue.push(node);
      pump();
    }
    if ('IntersectionObserver' in window) {
      geoObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          entry.target.querySelectorAll('[data-p1-comment-location]').forEach(node => {
            if (node.closest('.p1-c-main') === entry.target) enqueue(node);
          });
          geoObserver.unobserve(entry.target);
        });
      }, { rootMargin: '120px' });
      root.querySelectorAll('[data-p1-comment-location]').forEach(node => geoObserver.observe(node.closest('.p1-c-main')));
    } else {
      root.querySelectorAll('[data-p1-comment-location]').forEach(enqueue);
    }
  }

  document.addEventListener('click', event => {
    const button = event.target instanceof Element ? event.target.closest('button') : null;
    if (!button?.closest('.p1-conversations')) return;
    if (button.matches('[data-p1-switch-profile]')) {
      const form = button.closest('#respond').querySelector('.comment-form');
      const editing = form.classList.toggle('is-editing-profile');
      button.setAttribute('aria-expanded', String(editing));
      button.textContent = editing ? '收起资料' : '切换资料';
      if (editing) form.querySelector('#author')?.focus();
    }
    if (button.matches('[data-p1-c-emoji]')) {
      const textarea = button.closest('form').querySelector('#comment');
      textarea.setRangeText(button.dataset.p1CEmoji, textarea.selectionStart, textarea.selectionEnd, 'end');
      textarea.focus({ preventScroll: true });
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

  });
  document.addEventListener('input', event => {
    const textarea = event.target;
    if (!(textarea instanceof HTMLTextAreaElement) || !textarea.matches('.p1-conversations #comment')) return;
    if (textarea.scrollHeight > textarea.clientHeight) textarea.style.height = `${textarea.scrollHeight + 2}px`;
  });
  document.addEventListener('p1:page-leave', () => {
    controller?.abort();
    geoObserver?.disconnect();
  });
  document.addEventListener('p1:page-ready', mount);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
  else mount();
})();
;

// ================================================================
// subscriptions
// ================================================================
(() => {
	let controller;
	function initialize() {
	controller?.abort();
	controller = new AbortController();
	const signal = controller.signal;
	const page = document.querySelector('[data-feed-page]');
	if (!page) return;
	const entries = page.querySelector('[data-feed-entries]');
	const empty = page.querySelector('[data-feed-empty]');
	const more = page.querySelector('[data-feed-more]');
	const total = page.querySelector('[data-feed-total]');
	let currentPage = 1;
	let busy = false;

	async function load(nextPage) {
		if (busy) return;
		busy = true;
		page.setAttribute('aria-busy', 'true');
		more.disabled = true;
		try {
			const url = new URL(page.dataset.endpoint);
			url.searchParams.set('action', 'p1_feed_list');
			url.searchParams.set('page', String(nextPage));
			const response = await fetch(url, { credentials: 'same-origin', signal });
			const result = await response.json();
			if (signal.aborted || !page.isConnected) return;
			if (!response.ok || !result.success) throw new Error('订阅列表暂时无法加载。');
			entries.insertAdjacentHTML('beforeend', result.data.html);
			currentPage = nextPage;
			if (total) total.textContent = Number(result.data.total).toLocaleString('zh-CN');
			empty.hidden = result.data.total > 0;
			more.hidden = !result.data.more;
		} catch {
			if (signal.aborted || !page.isConnected) return;
			window.p1Toast?.show('订阅列表暂时无法加载，请稍后重试。', { type: 'error' });
		} finally {
			busy = false;
			more.disabled = false;
			page.removeAttribute('aria-busy');
		}
	}

	more.addEventListener('click', () => load(currentPage + 1));
	}
	document.addEventListener('p1:page-leave', () => controller?.abort());
	initialize();
	document.addEventListener('p1:page-ready', initialize);
})();
;

// ================================================================
// notes-page
// ================================================================
(() => {
	function initialize() {
	if (!document.querySelector('.p1-note-editor[open], .p1-note-detail[open]')) document.documentElement.classList.remove('p1-note-modal-open');
	const page = document.querySelector('.p1-notes-page');
	if (!page || page.dataset.noteInitialized) return;
	page.dataset.noteInitialized = '1';

	const editor = page.querySelector('#p1-note-editor');
	const detail = page.querySelector('[data-note-detail]');
	const board = page.querySelector('[data-note-board]');
	const openDialog = (dialog) => {
		if (!dialog) return;
		if (typeof dialog.showModal === 'function') dialog.showModal();
		else dialog.setAttribute('open', '');
		document.documentElement.classList.add('p1-note-modal-open');
	};
	const closeDialog = (dialog) => {
		if (!dialog) return;
		if (typeof dialog.close === 'function') dialog.close();
		else dialog.removeAttribute('open');
		if (!page.querySelector('dialog[open]')) document.documentElement.classList.remove('p1-note-modal-open');
	};

	const form = editor?.querySelector('[data-note-form]');
	let saving = false;
	let initial = '';
	let requestId = '';
	let files = [];
	let previewURLs = [];
	const uuid = () => globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
	const field = (name) => form?.elements.namedItem(name);
	const status = (message = '') => { const node = form?.querySelector('[data-note-form-status]'); if (node) node.textContent = message; };
	const model = () => form ? JSON.stringify([
		...['content', 'tags', 'location', 'link_url', 'link_label'].map(name => field(name).value),
		[...form.querySelectorAll('[name="keep_images[]"]')].map(node => node.value),
		files.map(file => [file.name, file.size, file.lastModified]),
	]) : '';
	const dirty = () => editor?.open && model() !== initial;
	const revoke = () => { previewURLs.forEach(url => URL.revokeObjectURL(url)); previewURLs = []; };
	const countWords = () => { const node = form?.querySelector('[data-note-words]'); if (node) node.textContent = String(Array.from(field('content').value).length); };
	const previews = () => {
		revoke();
		const target = form.querySelector('[data-note-upload-preview]');
		target.querySelectorAll('[data-note-new-image]').forEach(node => node.remove());
		files.forEach((file, index) => {
			const box = document.createElement('div');
			box.dataset.noteNewImage = String(index);
			const img = document.createElement('img');
			const url = URL.createObjectURL(file); previewURLs.push(url);
			img.src = url; img.alt = '待发布配图';
			const remove = document.createElement('button');
			remove.type = 'button'; remove.dataset.noteRemoveImage = ''; remove.textContent = '移除';
			remove.setAttribute('aria-label', `移除新添加的第 ${index + 1} 张配图`);
			box.append(img, remove); target.append(box);
		});
	};
	const maybeClose = () => {
		if (saving) return;
		if (dirty()) {
			editor.querySelector('[data-note-discard]').hidden = false;
			editor.querySelector('[data-note-continue]').focus();
		} else closeDialog(editor);
	};
	if (form) {
		initial = model(); requestId = uuid();
		form.addEventListener('input', () => { countWords(); requestId = uuid(); });
		field('images[]').addEventListener('change', () => {
			const selected = [...field('images[]').files];
			field('images[]').value = '';
			const kept = form.querySelectorAll('[name="keep_images[]"]').length;
			if (selected.length + files.length + kept > 4) { status('每条说说最多 4 张图片。'); return; }
			if (selected.some(file => file.size > Number(form.dataset.uploadLimit) || !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type))) {
				status('请选择单张大小限制以内的 JPEG、PNG、WebP 或 GIF 图片。'); return;
			}
			if ([...files, ...selected].reduce((total, file) => total + file.size, 0) > Number(form.dataset.uploadTotal)) {
				status('图片总大小超过服务器限制，请减少图片后重试。'); return;
			}
			files.push(...selected); requestId = uuid(); status(); previews();
		});
		form.addEventListener('click', (event) => {
			const button = event.target instanceof Element ? event.target.closest('button') : null;
			if (!button || saving) return;
			if (button.hasAttribute('data-note-suggestion')) {
				const tags = field('tags').value.split(/[,，\n]+/u).map(tag => tag.trim().replace(/^#+/, '')).filter(Boolean);
				const tag = button.dataset.noteSuggestion;
				if (!tags.includes(tag)) {
					if (tags.length >= 4) { status('关键词最多 4 个。'); return; }
					tags.push(tag); field('tags').value = tags.join('，'); requestId = uuid(); status();
				}
			}
			if (button.hasAttribute('data-note-remove-image')) {
				const box = button.closest('[data-note-kept-image], [data-note-new-image]');
				if (box?.hasAttribute('data-note-new-image')) files.splice(Number(box.dataset.noteNewImage), 1);
				else box?.remove();
				requestId = uuid(); status(); previews();
			}
		});
		form.addEventListener('submit', async (event) => {
			event.preventDefault();
			if (saving) return;
			if (!field('content').value.trim() && !files.length && !form.querySelector('[name="keep_images[]"]')) {
				status('写点什么，或者添加一张图片吧。'); field('content').focus(); return;
			}
			const body = new FormData(form);
			body.delete('images[]'); files.forEach(file => body.append('images[]', file));
			body.set('request_id', requestId);
			saving = true;
			const controls = [...form.querySelectorAll('input, textarea, button')];
			controls.forEach(control => control.disabled = true);
			status('正在保存这一刻…');
			try {
				const response = await fetch(form.dataset.endpoint, { method: 'POST', body, credentials: 'same-origin', cache: 'no-store' });
				let result;
				try { result = await response.json(); } catch { throw new Error('服务器没有返回保存结果，请稍后重试。'); }
				if (!response.ok || !result?.success) throw new Error(result?.data?.message || '保存失败，请稍后重试。');
				initial = model(); revoke(); saving = false; closeDialog(editor);
				if (window.p1Navigate) await window.p1Navigate(result.data.url);
				else location.assign(result.data.url);
			} catch (error) {
				status(error instanceof TypeError ? '网络连接失败，内容仍在这里，可以重试。' : error.message);
			} finally {
				saving = false; controls.forEach(control => control.disabled = false);
			}
		});
		editor.addEventListener('cancel', (event) => { event.preventDefault(); maybeClose(); });
		editor.querySelector('[data-note-continue]').addEventListener('click', () => {
			editor.querySelector('[data-note-discard]').hidden = true; field('content').focus();
		});
		editor.querySelector('[data-note-discard-confirm]').addEventListener('click', () => {
			if (saving) return;
			form.reset(); files = []; revoke();
			editor.querySelector('#p1-note-editor-title').textContent = field('note_id').value === '0' ? '写条说说' : '编辑说说';
			form.querySelector('[data-note-submit] span').textContent = field('note_id').value === '0' ? '发布' : '保存修改';
			form.querySelector('[data-note-upload-preview]').replaceChildren(...originalImages.map(node => node.cloneNode(true)));
			editor.querySelector('[data-note-discard]').hidden = true; status(); countWords(); requestId = uuid(); initial = model(); closeDialog(editor);
		});
	}
	const originalImages = form ? [...form.querySelectorAll('[data-note-kept-image]')].map(node => node.cloneNode(true)) : [];
	page.querySelector('[data-note-create]')?.addEventListener('click', () => {
		if (!form) return;
		form.reset(); files = []; revoke();
		for (const name of ['content', 'tags', 'location', 'link_url', 'link_label', 'version']) field(name).value = '';
		field('note_id').value = '0';
		form.querySelector('[data-note-upload-preview]').replaceChildren();
		editor.querySelector('#p1-note-editor-title').textContent = '写条说说';
		form.querySelector('[data-note-submit] span').textContent = '发布';
		editor.querySelector('[data-note-discard]').hidden = true;
		countWords(); status(); requestId = uuid(); initial = model(); openDialog(editor); field('content').focus({ preventScroll: true });
	});

	page.querySelector('[data-note-layout]')?.addEventListener('click', (event) => {
		const active = board?.classList.toggle('is-neat') ?? false;
		page.querySelector('.p1-note-board--remote')?.classList.toggle('is-neat', active);
		event.currentTarget.setAttribute('aria-pressed', String(active));
		event.currentTarget.setAttribute('aria-label', active ? '切换便签排列' : '切换整齐排列');
	});

	page.addEventListener('click', (event) => {
		if (!(event.target instanceof Element)) return;
		const close = event.target.closest('[data-note-close]');
		if (close) {
			if (close.closest('dialog') === editor) maybeClose();
			else closeDialog(close.closest('dialog'));
			return;
		}
		const opener = event.target.closest('[data-note-open]');
		if (!opener || !detail) return;
		const card = opener.closest('[data-note-card]');
		const content = card?.querySelector('.p1-note-text');
		const time = card?.querySelector('time');
		if (!content) return;
		detail.querySelector('[data-note-detail-text]').textContent = content.textContent;
		detail.querySelector('[data-note-detail-time]').textContent = time?.textContent ?? '';
		const extras = detail.querySelector('[data-note-detail-extras]');
		if (extras) {
			extras.replaceChildren();
			for (const element of card.querySelectorAll('[data-note-extras], .p1-note-link')) {
				extras.append(element.cloneNode(true));
			}
		}
		openDialog(detail);
	});

	for (const dialog of page.querySelectorAll('dialog')) {
		dialog.addEventListener('click', (event) => {
			if (event.target !== dialog) return;
			const bounds = dialog.getBoundingClientRect();
			if (event.clientX >= bounds.left && event.clientX <= bounds.right && event.clientY >= bounds.top && event.clientY <= bounds.bottom) return;
			if (dialog === editor) maybeClose(); else closeDialog(dialog);
		});
		dialog.addEventListener('close', () => { if (!page.querySelector('dialog[open]')) document.documentElement.classList.remove('p1-note-modal-open'); });
	}
	if (editor && (new URLSearchParams(location.search).has('edit_note') || location.hash === '#p1-note-editor')) {
		openDialog(editor);
	}
	}
	initialize();
	document.addEventListener('p1:page-ready', initialize);
})();
;

// ================================================================
// tooltip
// ================================================================
(() => {
	'use strict';

	const bubble = document.getElementById('p1-tooltip');
	if (!bubble) return;

	let active = null;
	let pending = null;
	let timer = 0;
	let previousDescription = null;
	const selector = 'a[href], button[title], button[aria-label], [role="button"][aria-label], [data-tooltip], [data-p1-tooltip-title], [title]';
	const excluded = '[data-no-tooltip], [data-p1-link-preview], .site-title, .primary-navigation, .single-post-categories, a[rel~="category"], .p1-archive-category > header, [aria-labelledby="p1-menu-categories-title"], .post-card-title-group, .post > h2, .single-post-title, .single-post-related-link, .p1-load-more';

	function tooltipsDisabled() {
		return document.body.matches('.search, .archive') || !!document.querySelector('.p1-notes-page, .p1-archive-index');
	}

	function prepare(root = document) {
		root.querySelectorAll('.site [title], .p1-quickbar [title]').forEach((element) => {
			element.dataset.p1TooltipTitle = element.getAttribute('title') || '';
			element.removeAttribute('title');
		});
	}

	function targetFor(node) {
		if (tooltipsDisabled() || !(node instanceof Element)) return null;
		const element = node.closest(selector);
		if (!element || !element.closest('.site, .p1-quickbar, .p1-article-toc') || element.closest(excluded)) return null;
		if (element.closest('.p1-article-toc') && !element.dataset.tooltip) return null;
		return element;
	}

	function labelFor(element) {
		if (element.hasAttribute('title')) {
			element.dataset.p1TooltipTitle = element.getAttribute('title') || '';
			element.removeAttribute('title');
		}
		const articleUrl = element.matches('.entry--reading a[href]') && !element.closest('[data-tooltip]')
			? element.href : '';
		const value = element.dataset.tooltip || articleUrl || element.dataset.p1TooltipTitle || element.getAttribute('aria-label') ||
			(element.matches('a[href]') ? element.textContent : '');
		return (value || '').replace(/\s+/g, ' ').trim();
	}

	function hide() {
		window.clearTimeout(timer);
		timer = 0;
		if (active && previousDescription !== null) {
			if (previousDescription) active.setAttribute('aria-describedby', previousDescription);
			else active.removeAttribute('aria-describedby');
		}
		previousDescription = null;
		active = null;
		pending = null;
		bubble.hidden = true;
		bubble.textContent = '';
	}

	function position(element) {
		const rect = element.getBoundingClientRect();
		const width = bubble.offsetWidth;
		const height = bubble.offsetHeight;
		const left = Math.max(12, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 12));
		const above = rect.top - height - 9;
		const adminBarBottom = document.getElementById('wpadminbar')?.getBoundingClientRect().bottom || 0;
		const top = above >= adminBarBottom + 12 ? above : rect.bottom + 9;
		bubble.style.left = `${left}px`;
		bubble.style.top = `${top}px`;
	}

	function show(element) {
		if (tooltipsDisabled() || !element.isConnected) return;
		const label = labelFor(element);
		if (!label) return;
		const host = element.closest('dialog[open]') || document.body;
		if (bubble.parentElement !== host) host.append(bubble);
		active = element;
		pending = null;
		bubble.textContent = label;
		bubble.hidden = false;
		previousDescription = element.getAttribute('aria-describedby') || '';
		element.setAttribute('aria-describedby', [previousDescription, bubble.id].filter(Boolean).join(' '));
		position(element);
	}

	function queue(element, delay) {
		if (element === active || element === pending) return;
		hide();
		if (!element) return;
		pending = element;
		if (delay) timer = window.setTimeout(() => show(element), delay);
		else show(element);
	}

	document.addEventListener('pointerover', (event) => {
		if (event.pointerType === 'touch') return;
		queue(targetFor(event.target), 130);
	});
	document.addEventListener('pointerout', (event) => {
		const element = targetFor(event.target);
		if (!element || element !== active && element !== pending) return;
		if (event.relatedTarget instanceof Node && element.contains(event.relatedTarget)) return;
		hide();
	});
	document.addEventListener('focusin', (event) => queue(targetFor(event.target), 0));
	document.addEventListener('focusout', (event) => {
		if (targetFor(event.target) === active) hide();
	});
	document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); });
	window.addEventListener('scroll', hide, true);
	window.addEventListener('resize', hide);
	document.addEventListener('p1:page-leave', hide);
	document.addEventListener('p1:page-ready', (event) => { hide(); prepare(event.detail.root); });
	document.addEventListener('p1:posts-updated', (event) => prepare(event.detail.root));
	prepare();
})();
;

// ================================================================
// article-links
// ================================================================
(() => {
	'use strict';

	function prepare(root = document) {
		if (!document.body.classList.contains('single-post')) return;
		root.querySelectorAll('.entry--reading a[href]').forEach((link) => {
			if (link.dataset.p1ArticleLink || link.closest('pre, .wp-block-button, [data-no-link-icons]') || link.querySelector('img, picture, video, audio, iframe, svg')) {
				return;
			}

			const href = link.getAttribute('href')?.trim();
			if (!href || href.startsWith('#') || !link.textContent.trim()) return;

			let url;
			try {
				url = new URL(href, window.location.href);
			} catch {
				return;
			}
			if (!['http:', 'https:'].includes(url.protocol)) return;

			const favicon = document.createElement('span');
			favicon.className = 'p1-content-link-favicon';
			favicon.setAttribute('aria-hidden', 'true');
			favicon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3Z"/></svg>';
			const faviconImage = document.createElement('img');
			faviconImage.alt = '';
			faviconImage.width = 16;
			faviconImage.height = 16;
			faviconImage.loading = 'lazy';
			faviconImage.decoding = 'async';
			faviconImage.setAttribute('data-no-litezoom', '');
			faviconImage.addEventListener('error', () => {
				faviconImage.remove();
				favicon.classList.add('is-missing');
			}, { once: true });
			faviconImage.src = `https://favicon.la/${encodeURIComponent(url.hostname.toLowerCase())}`;
			favicon.prepend(faviconImage);

			const icon = document.createElement('span');
			icon.className = 'p1-content-link-icon';
			icon.setAttribute('aria-hidden', 'true');
			// Font Awesome Pro 7.3.1 SVG supplied by the site owner (Commercial License).
			icon.innerHTML = '<svg viewBox="0 0 672 672" fill="currentColor"><path d="M316.2 316.2C305.3 327.1 305.3 344.9 316.2 355.8C327.1 366.7 344.9 366.7 355.8 355.8L483.6 228C506.4 248.2 528.6 264.3 538.5 271.2C546.9 277.1 556.7 280 566.5 280C593.8 280 616 257.9 616 230.5L616 112C616 81.1 590.9 56 560 56L441.5 56C414.2 56 392 78.1 392 105.5C392 115.3 394.9 125.1 400.8 133.5C407.8 143.4 423.9 165.6 444 188.4L316.2 316.2zM502.8 169.2C483.2 149.6 465.4 126.9 454.4 112L560 112L560 217.6C545.1 206.6 522.4 188.8 502.8 169.2zM84 392C84 427.6 85.7 457.9 91.2 483.2C96.7 508.8 106.4 531 123.7 548.3C141 565.6 163.2 575.3 188.8 580.8C214.1 586.3 244.5 588 280 588L392 588C427.6 588 457.9 586.3 483.2 580.8C508.8 575.3 531 565.6 548.3 548.3C565.6 531 575.3 508.8 580.8 483.2C586.3 457.9 588 427.5 588 392C588 376.5 575.5 364 560 364C544.5 364 532 376.5 532 392C532 426.4 530.2 452.1 526.1 471.4C522 490.4 516 501.5 508.7 508.7C501.4 515.9 490.4 522 471.4 526.1C452.1 530.3 426.4 532 392 532L280 532C245.6 532 219.9 530.2 200.6 526.1C181.6 522 170.5 516 163.3 508.7C156.1 501.4 150 490.4 145.9 471.4C141.7 452.1 140 426.4 140 392L140 280C140 245.6 141.8 219.9 145.9 200.6C150 181.6 156 170.5 163.3 163.3C170.6 156.1 181.6 150 200.6 145.9C219.9 141.7 245.6 140 280 140C295.5 140 308 127.5 308 112C308 96.5 295.5 84 280 84C244.4 84 214.1 85.7 188.8 91.2C163.2 96.7 141 106.4 123.7 123.7C106.4 141 96.7 163.2 91.2 188.8C85.7 214.1 84 244.4 84 280L84 392z"/></svg>';

			link.classList.add('p1-content-link');
			link.dataset.p1ArticleLink = '1';
			link.prepend(favicon);
			link.append(icon);
		});
	}

	prepare();
	document.addEventListener('p1:page-ready', (event) => prepare(event.detail.root));
	document.addEventListener('p1:posts-updated', (event) => prepare(event.detail.root));
})();
;

// ================================================================
// Article tables: a shared scroll container for classic and block editor markup.
// ================================================================
(() => {
	'use strict';
	function prepare(root = document) {
		if (!document.body.classList.contains('single-post')) return;
		root.querySelectorAll('.entry--reading table').forEach((table) => {
			if (table.closest('.p1-reading-table') || table.parentElement?.closest('table')) return;
			const container = document.createElement('div');
			container.className = 'p1-reading-table';
			container.tabIndex = 0;
			container.setAttribute('role', 'region');
			container.setAttribute('aria-label', table.caption?.textContent.trim() || '文章表格');
			table.before(container);
			container.append(table);
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => prepare(), { once: true });
	else prepare();
	document.addEventListener('p1:page-ready', (event) => prepare(event.detail.root));
})();
;

// ================================================================
// article-link-preview — WordPress.com mShots, as used by ShanYing.
// ================================================================
(() => {
	'use strict';
	let cleanup = () => {};

	function mount() {
		cleanup();
		if (!document.body.classList.contains('single-post')) return;
		const links = [...document.querySelectorAll('.entry--reading a.p1-content-link[href]')].filter((link) => {
			if (link.closest('pre, code, [data-no-tooltip], [data-no-link-preview]')) return false;
			const url = new URL(link.href);
			return ['http:', 'https:'].includes(url.protocol) && url.hostname !== window.location.hostname && !url.username && !url.password;
		});
		if (!links.length) return;

		const life = new AbortController();
		const preview = document.createElement('div');
		preview.id = 'p1-link-preview';
		preview.className = 'p1-link-preview';
		preview.setAttribute('role', 'tooltip');
		preview.hidden = true;
		const media = document.createElement('div');
		media.className = 'p1-link-preview-media';
		media.dataset.loadingText = '正在加载预览…';
		const shot = document.createElement('img');
		shot.width = 640;
		shot.height = 400;
		shot.decoding = 'async';
		shot.referrerPolicy = 'no-referrer';
		shot.setAttribute('data-no-litezoom', '');
		const caption = document.createElement('div');
		caption.className = 'p1-link-preview-caption';
		media.append(shot);
		preview.append(media, caption);
		document.body.append(preview);
		let timer = 0;
		let active = null;

		function hide() {
			window.clearTimeout(timer);
			timer = 0;
			preview.hidden = true;
			if (active) {
				const ids = (active.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== preview.id);
				if (ids.length) active.setAttribute('aria-describedby', ids.join(' '));
				else active.removeAttribute('aria-describedby');
			}
			active = null;
		}

		function position(link) {
			const rect = link.getBoundingClientRect();
			const width = preview.offsetWidth;
			const height = preview.offsetHeight;
			const upper = Math.max(12, (document.getElementById('wpadminbar')?.getBoundingClientRect().bottom || 0) + 12);
			const above = rect.top - height - 12;
			const top = above >= upper ? above : rect.bottom + 12;
			preview.style.left = `${Math.max(12, Math.min(window.innerWidth - width - 12, rect.left + rect.width / 2 - width / 2))}px`;
			preview.style.top = `${Math.max(upper, Math.min(window.innerHeight - height - 12, top))}px`;
		}

		function show(link) {
			hide();
			timer = window.setTimeout(() => {
				if (!link.isConnected) return;
				active = link;
				const url = new URL(link.href);
				caption.textContent = url.hostname;
				shot.alt = `${url.hostname} 网站预览`;
				shot.hidden = true;
				media.hidden = false;
				media.classList.add('is-loading');
				shot.onload = () => {
					if (active !== link) return;
					media.classList.remove('is-loading');
					shot.hidden = false;
				};
				shot.onerror = () => {
					if (active !== link) return;
					media.hidden = true;
					position(link);
				};
				// Request screenshots only after a deliberate hover, never during page load.
				shot.src = `https://s.wordpress.com/mshots/v1/${encodeURIComponent(url.href)}?w=640&h=400`;
				if (shot.complete && shot.naturalWidth) shot.onload();
				preview.hidden = false;
				const ids = new Set((link.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
				ids.add(preview.id);
				link.setAttribute('aria-describedby', [...ids].join(' '));
				position(link);
			}, 350);
		}

		links.forEach((link) => {
			link.dataset.p1LinkPreview = '1';
			link.addEventListener('mouseenter', () => {
				if (window.matchMedia('(hover: hover)').matches) show(link);
			}, { signal: life.signal });
			link.addEventListener('mouseleave', hide, { signal: life.signal });
			link.addEventListener('focus', () => {
				if (link.matches(':focus-visible')) show(link);
			}, { signal: life.signal });
			link.addEventListener('blur', hide, { signal: life.signal });
			link.addEventListener('click', hide, { signal: life.signal });
		});
		document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); }, { signal: life.signal });
		window.addEventListener('scroll', hide, { capture: true, passive: true, signal: life.signal });
		window.addEventListener('resize', hide, { signal: life.signal });
		cleanup = () => {
			hide();
			life.abort();
			shot.onload = shot.onerror = null;
			preview.remove();
			links.forEach((link) => delete link.dataset.p1LinkPreview);
			cleanup = () => {};
		};
	}

	mount();
	document.addEventListener('p1:page-leave', () => cleanup());
	document.addEventListener('p1:page-ready', mount);
	document.addEventListener('p1:posts-updated', mount);
})();
;

// ================================================================
// article-code
// ================================================================
(() => {
	'use strict';

	function copyCode(code) {
		// Try the synchronous fallback while the click gesture is still active.
		const field = document.createElement('textarea');
		field.value = code;
		field.readOnly = true;
		field.style.cssText = 'position:fixed;left:0;top:0;width:2px;height:2px;padding:0;border:0;opacity:0;font-size:16px';
		document.body.append(field);
		let copied = false;
		try {
			field.focus({ preventScroll: true });
			field.select();
			field.setSelectionRange(0, field.value.length);
			copied = document.execCommand('copy');
		} catch {
			// Clipboard API below may still be available.
		} finally {
			field.remove();
		}
		if (copied) {
			return Promise.resolve();
		}
		return navigator.clipboard?.writeText
			? navigator.clipboard.writeText(code)
			: Promise.reject(new Error('Clipboard unavailable'));
	}

	function createCopyControl(readText, readLabel, failureMessage = '复制失败，请手动选择代码复制') {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'p1-code-copy';
		button.innerHTML = '<svg class="p1-code-copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg><svg class="p1-code-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>';
		let resetTimer;
		function reset() {
			window.clearTimeout(resetTimer);
			button.setAttribute('aria-label', readLabel());
			button.dataset.tooltip = readLabel();
			button.classList.remove('is-copied');
		}
		reset();
		button.addEventListener('click', async () => {
			if (button.disabled) return;
			window.clearTimeout(resetTimer);
			button.disabled = true;
			try {
				await copyCode(readText());
				button.setAttribute('aria-label', '复制成功');
				button.dataset.tooltip = '复制成功';
				button.classList.add('is-copied');
				window.p1Toast?.show('复制成功', { type: 'copy' });
			} catch {
				button.classList.remove('is-copied');
				button.setAttribute('aria-label', '复制失败，请重试');
				button.dataset.tooltip = failureMessage;
				window.p1Toast?.show(failureMessage, { type: 'error' });
			} finally {
				button.disabled = false;
				if (button.isConnected) button.focus({ preventScroll: true });
				resetTimer = window.setTimeout(() => { if (button.isConnected) reset(); }, 2500);
			}
		});
		return { button, reset };
	}

	function prepare(root = document) {
		root.querySelectorAll('.single-post-copyright-source').forEach((source) => {
			const link = source.querySelector('a.single-post-copyright-url');
			if (!link || source.querySelector('.p1-source-copy')) return;
			const copy = createCopyControl(() => link.href, () => '复制原文链接', '复制失败，请手动复制原文链接');
			copy.button.className = 'p1-source-copy';
			source.append(copy.button);
		});
		for (const pre of root.querySelectorAll('.entry pre')) {
			let code = pre.querySelector(':scope > code');
			if (!code && [...pre.childNodes].every((node) => node.nodeType === Node.TEXT_NODE)) {
				code = document.createElement('code');
				code.textContent = pre.textContent;
				pre.replaceChildren(code);
			}
			if (!code) {
				continue;
			}

			const languageClass = [...code.classList, ...pre.classList].find((name) => /^lang(?:uage)?-/.test(name));
			const language = languageClass?.replace(/^lang(?:uage)?-/, '');
			if (language && !['none', 'plaintext', 'text'].includes(language)) {
				pre.dataset.language = language.toUpperCase();
			}
			if (code.textContent.length < 100000 && window.hljs && !code.dataset.highlighted) {
				window.hljs.highlightElement(code);
			}
			if (pre.querySelector('.p1-code-copy')) {
				continue;
			}

			const text = code.textContent;
			const lines = text.replace(/\r\n?/g, '\n').split('\n');
			while (lines.length > 1 && lines.at(-1) === '') lines.pop();
			const singleLine = lines.length === 1;
			pre.classList.toggle('p1-code--single', singleLine);
			const whole = createCopyControl(() => text, () => singleLine ? '复制代码' : '复制整段代码');
			pre.append(whole.button);
			if (singleLine) continue;

			let lineIndex = 0;
			const row = createCopyControl(() => lines[lineIndex], () => `复制第 ${lineIndex + 1} 行`);
			row.button.classList.add('p1-code-line-copy');
			row.button.hidden = true;
			pre.append(row.button);
			function hideRow() {
				if (document.activeElement !== row.button && !row.button.disabled) row.button.hidden = true;
			}
			pre.addEventListener('pointermove', (event) => {
				if (event.pointerType === 'touch' || row.button.disabled || event.target.closest('.p1-code-line-copy')) return;
				const rect = code.getBoundingClientRect();
				const lineHeight = Number.parseFloat(getComputedStyle(code).lineHeight);
				const index = Math.floor((event.clientY - rect.top + code.scrollTop) / lineHeight);
				if (!Number.isFinite(index) || index < 0 || index >= lines.length || !lines[index].trim() || event.target.closest('.p1-code-copy')) {
					hideRow();
					return;
				}
				if (index !== lineIndex) {
					lineIndex = index;
					row.reset();
				}
				row.button.style.top = `${rect.top - pre.getBoundingClientRect().top - pre.clientTop + (index + 0.5) * lineHeight - code.scrollTop}px`;
				row.button.hidden = false;
			});
			pre.addEventListener('pointerleave', hideRow);
			row.button.addEventListener('blur', () => { if (!pre.matches(':hover')) row.button.hidden = true; });
		}
	}

	prepare();
	document.addEventListener('p1:page-ready', (event) => prepare(event.detail.root));
	document.addEventListener('p1:assets-ready', (event) => prepare(event.detail.root));
})();
;

// ================================================================
// litezoom-init
// ================================================================
(() => {
	'use strict';
	let bound = false;

	function prepare() {
		if (bound || !window.LiteZoom) return;
		// LiteZoom delegates image clicks, including images added by PJAX.
		window.LiteZoom.bind('.entry img', {
			mode: 'full',
			group: (image) => image.closest('article')?.id || 'p1-content',
			caption: (image) => image.closest('figure')?.querySelector('figcaption')?.textContent?.trim() || image.alt || '',
			exclude: (image) => image.matches('.emoji, .wp-smiley, .p1-link-favicon') || Boolean(image.closest('[data-no-litezoom]')),
		});
		bound = true;
	}
	prepare();
	document.addEventListener('p1:page-ready', prepare);
	document.addEventListener('p1:assets-ready', (event) => {
		if (event.detail.url.includes('litezoom')) prepare();
	});
})();
;

// ================================================================
// header-actions
// ================================================================
(function () {
	'use strict';
	function initialize() {

	const menu = document.querySelector('.header-menu');
	const menuCheckbox = document.getElementById('checkbox');
	const menuToggle = menu?.querySelector('.toggle');
	const menuDialog = document.getElementById('p1-menu-dialog');
	if (menu && menuCheckbox && menuToggle && menuDialog) {
		const closeButton = menuDialog.querySelector('.p1-menu-dialog-close');

		function setMenuOpen(open) {
			menuCheckbox.checked = open;
			menuToggle.setAttribute('aria-expanded', String(open));
			menuToggle.setAttribute('aria-label', open ? menuToggle.dataset.closeLabel : menuToggle.dataset.openLabel);
			if (open && !menuDialog.open) {
				menuDialog.showModal();
				document.documentElement.classList.add('p1-modal-open');
				document.body.classList.add('p1-modal-open');
				closeButton?.focus();
			} else if (!open && menuDialog.open) {
				menuDialog.close();
			}
		}

		menuCheckbox.addEventListener('change', function () {
			setMenuOpen(menuCheckbox.checked);
		});

		menuToggle.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				setMenuOpen(!menuCheckbox.checked);
			}
		});

		closeButton?.addEventListener('click', function () {
			setMenuOpen(false);
		});

		menuDialog.addEventListener('click', function (event) {
			if (event.target === menuDialog) {
				setMenuOpen(false);
			}
		});

		menuDialog.addEventListener('close', function () {
			menuCheckbox.checked = false;
			menuToggle.setAttribute('aria-expanded', 'false');
			menuToggle.setAttribute('aria-label', menuToggle.dataset.openLabel);
			document.documentElement.classList.remove('p1-modal-open');
			document.body.classList.remove('p1-modal-open');
			menuToggle.focus({ preventScroll: true });
		});
	}

	const rssButton = document.querySelector('.footer-rss-button');

	async function copyText(value) {
		if (navigator.clipboard?.writeText && window.isSecureContext) {
			try {
				await navigator.clipboard.writeText(value);
				return true;
			} catch (error) {
				// Try the document selection fallback below.
			}
		}

		const field = document.createElement('textarea');
		field.value = value;
		field.setAttribute('readonly', '');
		field.style.position = 'fixed';
		field.style.insetBlockStart = '0';
		field.style.insetInlineStart = '0';
		field.style.width = '1px';
		field.style.height = '1px';
		field.style.opacity = '0';
		document.body.appendChild(field);
		field.focus();
		field.select();
		field.setSelectionRange(0, value.length);

		try {
			return document.execCommand('copy');
		} catch (error) {
			return false;
		} finally {
			field.remove();
			rssButton?.focus({ preventScroll: true });
		}
	}

	if (rssButton) {
		let resetTimer;
		rssButton.addEventListener('click', async function (event) {
			if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			event.preventDefault();
			const copied = await copyText(rssButton.href);
			rssButton.classList.toggle('is-copied', copied);
			window.p1Toast?.show(copied ? '复制成功' : '复制失败', {
				type: copied ? 'copy' : 'error',
				detail: copied ? rssButton.dataset.copiedMessage : rssButton.dataset.copyFailedMessage,
			});

			window.clearTimeout(resetTimer);
			resetTimer = window.setTimeout(function () {
				rssButton.classList.remove('is-copied');
			}, 2500);
		});
	}

	const randomButton = document.querySelector('.quickbar-random-button');
	if (randomButton) {
		randomButton.addEventListener('click', function (event) {
			if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			event.preventDefault();
			if (randomButton.classList.contains('is-loading')) {
				return;
			}
			randomButton.classList.add('is-loading');
			randomButton.setAttribute('aria-busy', 'true');
			window.setTimeout(function () {
				if (!randomButton.isConnected) return;
				if (window.p1Navigate) window.p1Navigate(randomButton.href);
				else window.location.assign(randomButton.href);
			}, 1000);
		});
	}
	}
	let topFrame = 0;
	function updateBackToTop() {
		topFrame = 0;
		const button = document.querySelector('[data-p1-back-to-top]');
		if (button) button.hidden = window.scrollY <= 400;
	}
	function scheduleBackToTop() {
		if (!topFrame) topFrame = requestAnimationFrame(updateBackToTop);
	}
	initialize();
	scheduleBackToTop();
	window.addEventListener('scroll', scheduleBackToTop, { passive: true });
	document.addEventListener('p1:page-ready', () => {
		initialize();
		const button = document.querySelector('[data-p1-back-to-top]');
		if (button) button.hidden = true;
		// PJAX restores scroll on its next frame; evaluate after that restoration.
		requestAnimationFrame(() => requestAnimationFrame(scheduleBackToTop));
	});
	document.addEventListener('click', (event) => {
		const top = event.target.closest('[data-p1-back-to-top]');
		if (!top || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		event.preventDefault();
		window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
	});
	window.addEventListener('pageshow', function () {
		scheduleBackToTop();
		const button = document.querySelector('.quickbar-random-button');
		button?.classList.remove('is-loading');
		button?.removeAttribute('aria-busy');
	});
})();
;

// ================================================================
// post-views
// ================================================================
(function () {
	'use strict';

	const config = p1RuntimeConfig.views;
	if (!config) {
		return;
	}

	const formatter = new Intl.NumberFormat(document.documentElement.lang || undefined);

	function updateCount(postId, count) {
		document.querySelectorAll('[data-p1-view-count]').forEach((counter) => {
			if (counter.dataset.p1ViewCount !== String(postId)) {
				return;
			}
			counter.textContent = formatter.format(count);
		});
	}

	async function request(url, options = {}) {
		const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error('Could not refresh article views.');
		}
		return result.data;
	}

	async function refreshAndRecord(root = document, recordArticle = false) {
		const article = root.querySelector('[data-p1-view-track]');
		const counters = Array.from(root.querySelectorAll('[data-p1-view-count]'));
		const ids = [...new Set(counters.map((counter) => counter.dataset.p1ViewCount))];
		if (recordArticle && article) {
			ids.push(article.dataset.p1ViewTrack);
		}
		if (!ids.length) {
			return;
		}
		try {
			let nonce = '';
			for (let index = 0; index < ids.length; index += 40) {
				const query = new URLSearchParams({ action: 'p1_get_post_view_state', post_ids: ids.slice(index, index + 40).join(',') });
				const data = await request(`${config.ajaxUrl}?${query}`);
				nonce = data.nonce;
				Object.entries(data.posts).forEach(([postId, count]) => updateCount(postId, count));
			}
			if (recordArticle && article && nonce) {
				const body = new URLSearchParams({ action: 'p1_record_post_view', post_id: article.dataset.p1ViewTrack, nonce });
				const data = await request(config.ajaxUrl, {
					method: 'POST',
					keepalive: true,
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body,
				});
				updateCount(data.post_id, data.count);
			}
		} catch (error) {
			// The server-rendered count remains visible if the refresh fails.
		}
	}

	refreshAndRecord(document, true);
	window.addEventListener('pageshow', (event) => {
		if (event.persisted) {
			refreshAndRecord(document, true);
		}
	});
	document.addEventListener('p1:posts-updated', (event) => refreshAndRecord(event.detail.root));
	document.addEventListener('p1:page-ready', (event) => refreshAndRecord(event.detail.root, event.detail.recordView !== false));
})();
;

// ================================================================
// post-likes
// ================================================================
(function () {
	'use strict';

	const config = p1RuntimeConfig.likes;
	if (!config) {
		return;
	}

	const pending = new Set();
	const revisions = new Map();
	let refreshGeneration = 0;
	const formatter = new Intl.NumberFormat(document.documentElement.lang || undefined);
	const getButtons = (root = document) => Array.from(root.querySelectorAll('[data-p1-like-post]'));
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

	function celebrateLike(button) {
		if (reducedMotion.matches || !button.isConnected) {
			return false;
		}

		const rect = button.getBoundingClientRect();
		const isArticle = Boolean(button.closest('.single-post-like'));
		const originX = rect.left + rect.width / 2;
		const originY = rect.top + rect.height / 2;
		const confetti = document.createElement('div');
		confetti.className = `p1-like-confetti${isArticle ? ' p1-like-confetti--article' : ''}`;
		confetti.setAttribute('aria-hidden', 'true');

		const count = isArticle ? 28 : 16;
		const radius = isArticle ? 105 : 75;
		const colors = ['#ff6b8a', '#ff9b54', '#ffd166', '#8de28a', '#64c9ff', '#b794ff'];
		const colorOffset = Math.floor(Math.random() * colors.length);
		for (let index = 0; index < count; index += 1) {
			const piece = document.createElement('span');
			piece.style.setProperty('--p1-confetti-color', colors[(index + colorOffset) % colors.length]);
			const angle = (2 * Math.PI * index / count) + (Math.random() - 0.5) * 0.35;
			const distance = radius * (0.55 + Math.random() * 0.45);
			piece.style.setProperty('--p1-confetti-origin-x', `${originX}px`);
			piece.style.setProperty('--p1-confetti-origin-y', `${originY}px`);
			piece.style.setProperty('--p1-confetti-x', `${Math.cos(angle) * distance}px`);
			piece.style.setProperty('--p1-confetti-y', `${Math.sin(angle) * distance + 24}px`);
			piece.style.setProperty('--p1-confetti-rotation', `${(Math.random() - 0.5) * 720}deg`);
			piece.style.setProperty('--p1-confetti-delay', `${Math.random() * 100}ms`);
			confetti.appendChild(piece);
		}

		document.body.appendChild(confetti);
		window.setTimeout(() => confetti.remove(), 1000);
		return true;
	}

	function setState(postId, count, liked) {
		getButtons().filter((button) => button.dataset.p1LikePost === String(postId)).forEach((button) => {
			button.classList.toggle('is-liked', liked);
			button.disabled = liked || pending.has(String(postId));
			button.setAttribute('aria-disabled', String(button.disabled));
			button.setAttribute('aria-pressed', String(liked));
			button.setAttribute('aria-label', `${liked ? (config.likedLabel || '已点赞') : config.likeLabel}: ${formatter.format(count)}`);
			const countLabel = button.querySelector('.p1-like-count');
			if (countLabel) {
				countLabel.textContent = formatter.format(count);
			}
		});
	}

	function setCommentState(postId, commented) {
		document.querySelectorAll('[data-p1-comment-post]').forEach((link) => {
			if (link.dataset.p1CommentPost !== String(postId)) return;
			link.classList.toggle('is-commented', commented);
			const icon = link.querySelector('.fa-comment');
			icon?.classList.toggle('fa-solid', commented);
			icon?.classList.toggle('fa-regular', !commented);
			link.dataset.p1CommentLabel ||= link.getAttribute('aria-label') || '评论';
			link.setAttribute('aria-label', `${link.dataset.p1CommentLabel}${commented ? '，你已参与盖楼' : ''}`);
		});
	}

	async function request(url, options = {}) {
		const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
		const result = await response.json();
		if (!response.ok || !result.success) {
			throw new Error(result.data?.message || config.errorLabel);
		}
		return result.data;
	}

	async function loadState(root = document) {
		const generation = refreshGeneration;
		const buttons = getButtons(root);
		const snapshot = new Map(buttons.map((button) => [button.dataset.p1LikePost, revisions.get(button.dataset.p1LikePost) || 0]));
		const commentLinks = [...root.querySelectorAll('[data-p1-comment-post]')];
		const ids = [...new Set([...buttons.map((button) => button.dataset.p1LikePost), ...commentLinks.map((link) => link.dataset.p1CommentPost)])];
		if (!ids.length) {
			return;
		}
		buttons.forEach((button) => { button.disabled = true; });
		try {
			for (let index = 0; index < ids.length; index += 40) {
				const query = new URLSearchParams({ action: 'p1_get_like_state', post_ids: ids.slice(index, index + 40).join(',') });
				const data = await request(`${config.ajaxUrl}?${query}`);
				if (generation !== refreshGeneration) return;
				config.nonce = data.nonce;
				Object.entries(data.posts).forEach(([postId, state]) => {
					if (!pending.has(postId) && snapshot.get(postId) === (revisions.get(postId) || 0)) setState(postId, state.count, state.liked);
					setCommentState(postId, Boolean(state.commented));
				});
			}
		} catch (error) {
			// Keep the rendered counts available if the refresh is temporarily unavailable.
		} finally {
			if (generation === refreshGeneration) buttons.forEach((button) => { button.disabled = button.classList.contains('is-liked') || pending.has(button.dataset.p1LikePost); });
		}
	}

	document.addEventListener('click', async (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-p1-like-post]') : null;
		if (!button || button.disabled || button.classList.contains('is-liked') || pending.has(button.dataset.p1LikePost)) {
			return;
		}
		const postId = button.dataset.p1LikePost;
		pending.add(postId);
		revisions.set(postId, (revisions.get(postId) || 0) + 1);
		getButtons().filter((item) => item.dataset.p1LikePost === postId).forEach((item) => { item.disabled = true; });
		const feedback = button.closest('.p1-like-control')?.querySelector('.p1-like-feedback');
		if (feedback) {
			feedback.textContent = '';
		}
		try {
			const body = new URLSearchParams({ action: 'p1_toggle_post_like', post_id: button.dataset.p1LikePost, nonce: config.nonce });
			const data = await request(config.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body,
			});
			setState(button.dataset.p1LikePost, data.count, data.liked);
			if (!button.isConnected) return;
			if (data.added) {
				const celebrated = celebrateLike(button);
				window.p1Toast?.show(celebrated ? '点赞成功 · 撒花成功' : '点赞成功', {
					type: 'like',
					detail: celebrated ? '撒花成功，感谢你的支持' : '感谢你的支持',
				});
			} else {
				window.p1Toast?.show('已经点过赞了，感谢你的支持', { type: 'like' });
			}
		} catch (error) {
			if (feedback) {
				feedback.textContent = config.errorLabel;
				window.setTimeout(() => { feedback.textContent = ''; }, 3500);
			}
		} finally {
			pending.delete(postId);
			getButtons().filter((item) => item.dataset.p1LikePost === postId).forEach((item) => { item.disabled = item.classList.contains('is-liked'); item.setAttribute('aria-disabled', String(item.disabled)); });
		}
	});

	document.addEventListener('p1:page-leave', () => { refreshGeneration += 1; });
	loadState();
	document.addEventListener('p1:posts-updated', (event) => loadState(event.detail.root));
	document.addEventListener('p1:page-ready', (event) => loadState(event.detail.root));
})();
;

// ================================================================
// pjax
// ================================================================
(() => {
	'use strict';

	const config = p1RuntimeConfig.pjax;
	const indicator = document.getElementById('p1-page-loading');
	if (!config || !indicator || !window.fetch || !window.history?.pushState) return;

	const home = new URL(config.homeUrl, location.href);
	const activeLoads = new Set();
	let request = null;
	let loadMoreRequest = null;
	let currentUrl = location.href;
	let navigationId = 0;
	const loadedScripts = new Map();
	history.scrollRestoration = 'manual';

	function startLoading() {
		const token = Symbol('loading');
		activeLoads.add(token);
		indicator.hidden = false;
		document.body.classList.add('p1-is-loading');
		document.querySelector('.site-content')?.setAttribute('aria-busy', 'true');
		return token;
	}

	function stopLoading(token) {
		activeLoads.delete(token);
		if (activeLoads.size) return;
		indicator.hidden = true;
		document.body.classList.remove('p1-is-loading');
		document.querySelector('.site-content')?.removeAttribute('aria-busy');
	}

	window.p1Loading = { start: startLoading, stop: stopLoading };

	function isThemePage(page) {
		return Boolean(page.querySelector('.site > .site-header') && page.querySelector('.site-content #main.site-main'));
	}

	function isLocalPage(url) {
		return url.origin === home.origin && url.pathname.startsWith(home.pathname) &&
			!url.pathname.match(/\/(?:wp-admin|wp-json)(?:\/|$)|\/(?:wp-login|wp-comments-post|xmlrpc)\.php$/) &&
			!url.searchParams.has('preview') && !url.searchParams.has('customize_changeset_uuid');
	}

	function loadScript(url) {
		if (loadedScripts.has(url)) return loadedScripts.get(url);
		const promise = new Promise((resolve, reject) => {
			const script = document.createElement('script');
			const timer = setTimeout(() => reject(new Error('脚本加载超时')), 5000);
			script.src = url;
			script.onload = () => {
				clearTimeout(timer);
				resolve();
				document.dispatchEvent(new CustomEvent('p1:assets-ready', { detail: { root: document.querySelector('.site'), url } }));
			};
			script.onerror = () => { clearTimeout(timer); reject(new Error('脚本加载失败')); };
			document.head.append(script);
		});
		loadedScripts.set(url, promise);
		promise.catch(() => loadedScripts.delete(url));
		return promise;
	}

	async function prepareAssets(page, pageUrl) {
		const styles = [...page.querySelectorAll('head link[rel="stylesheet"][href]')];
		await Promise.all(styles.map((style) => {
			const href = new URL(style.getAttribute('href'), pageUrl).href;
			if ([...document.querySelectorAll('head link[rel="stylesheet"][href]')].some((existing) => existing.href === href)) return Promise.resolve();
			return new Promise((resolve) => {
				const link = document.createElement('link');
				link.rel = 'stylesheet';
				link.href = href;
				link.media = style.media;
				link.onload = resolve;
				link.onerror = resolve;
				document.head.append(link);
				setTimeout(resolve, 3000);
			});
		}));
		const main = page.querySelector('#main');
		const scripts = [];
		if (main.querySelector('.entry pre') && !window.hljs) scripts.push(loadScript(config.highlightUrl));
		if (main.querySelector('.entry img') && !window.LiteZoom) scripts.push(loadScript(config.litezoomUrl));
		await Promise.allSettled(scripts);
	}

	function syncHead(page) {
		document.title = page.title || document.title;
		for (const selector of ['link[rel="canonical"]', 'meta[name="description"]', 'meta[name="robots"]']) {
			document.head.querySelector(selector)?.remove();
			const next = page.head.querySelector(selector);
			if (next) document.head.append(document.importNode(next, true));
		}
		for (const style of page.head.querySelectorAll('style[id]')) {
			const current = document.getElementById(style.id);
			if (current?.tagName === 'STYLE') current.textContent = style.textContent;
			else document.head.append(document.importNode(style, true));
		}
	}

	function preserveNavigation(site, nextSite) {
		const currentNav = site.querySelector('#navigation');
		const nextNav = nextSite.querySelector('#navigation');
		if (!currentNav || !nextNav) return;
		const currentLinks = [...currentNav.querySelectorAll('ul a[href]')];
		const nextLinks = [...nextNav.querySelectorAll('ul a[href]')];
		const currentItems = [...currentNav.querySelectorAll('li')];
		const nextItems = [...nextNav.querySelectorAll('li')];
		const currentSearch = currentNav.querySelector('.navigation-search-input');
		const nextSearch = nextNav.querySelector('.navigation-search-input');
		if (currentLinks.length !== nextLinks.length || currentItems.length !== nextItems.length ||
			Boolean(currentSearch) !== Boolean(nextSearch) ||
			currentLinks.some((link, index) => link.getAttribute('href') !== nextLinks[index].getAttribute('href') || link.textContent !== nextLinks[index].textContent)) return;

		currentItems.forEach((item, index) => { item.className = nextItems[index].className; });
		currentLinks.forEach((link, index) => {
			link.className = nextLinks[index].className;
			const current = nextLinks[index].getAttribute('aria-current');
			if (current === null) link.removeAttribute('aria-current');
			else link.setAttribute('aria-current', current);
		});
		if (currentSearch) currentSearch.value = nextSearch.value;
		nextNav.replaceWith(currentNav);
	}

	function commitPage(page, url, mode, scrollY, options = {}) {
		const nextSite = page.querySelector('.site');
		const site = document.querySelector('.site');
		const replacement = document.importNode(nextSite, true);
		document.dispatchEvent(new CustomEvent('p1:page-leave'));
		preserveNavigation(site, replacement);
		site.replaceWith(replacement);
		document.documentElement.classList.remove('p1-modal-open');
		const quickbar = document.querySelector('.p1-quickbar');
		const nextQuickbar = page.querySelector('.p1-quickbar');
		if (nextQuickbar) {
			const nextQuickbarElement = document.importNode(nextQuickbar, true);
			if (quickbar) quickbar.replaceWith(nextQuickbarElement);
			else indicator.before(nextQuickbarElement);
		} else quickbar?.remove();
		const nextEnd = page.querySelector('#p1-page-end');
		if (nextEnd && !document.getElementById('p1-page-end')) indicator.before(document.importNode(nextEnd, true));
		document.body.className = page.body.className;
		for (const name of ['color-scheme', 'heading-font', 'body-font']) {
			if (page.body.dataset[name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())]) {
				document.body.setAttribute(`data-${name}`, page.body.getAttribute(`data-${name}`));
			}
		}
		syncHead(page);
		if (mode === 'push') history.pushState({ p1Page: true, scrollY: 0 }, '', url);
		if (mode === 'replace') history.replaceState({ p1Page: true, scrollY: 0 }, '', url);
		currentUrl = location.href;
		const root = document.querySelector('.site');
		document.dispatchEvent(new CustomEvent('p1:page-ready', { detail: { root, recordView: options.recordView !== false } }));
		const hash = new URL(url).hash;
		requestAnimationFrame(() => {
			let anchor = null;
			if (hash) {
				try { anchor = document.getElementById(decodeURIComponent(hash.slice(1))); } catch { /* Ignore an invalid fragment. */ }
			}
			if (typeof scrollY === 'number') window.scrollTo({ top: scrollY, behavior: 'auto' });
			else if (anchor) anchor.scrollIntoView();
			else window.scrollTo({ top: 0, behavior: 'auto' });
			const main = document.getElementById('main');
			main?.setAttribute('tabindex', '-1');
			if (!hash) main?.focus({ preventScroll: true });
		});
	}

	async function fetchPage(url, options = {}) {
		const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
		if (!response.ok || !response.headers.get('content-type')?.includes('text/html')) throw new Error('页面暂时无法加载');
		const destination = response.url || url;
		if (!isLocalPage(new URL(destination))) throw new Error('页面需要完整跳转');
		const page = new DOMParser().parseFromString(await response.text(), 'text/html');
		if (!isThemePage(page)) throw new Error('页面需要完整跳转');
		return { page, destination };
	}

	async function loadMore(link) {
		const control = link.closest('.p1-load-more');
		const main = control?.closest('#main');
		if (!control || !main || control.classList.contains('is-loading')) return;
		const controller = new AbortController();
		loadMoreRequest?.abort();
		loadMoreRequest = controller;
		const message = control.querySelector('.p1-load-more-message');
		if (message) { message.hidden = true; message.textContent = ''; }
		control.classList.add('is-loading');
		control.setAttribute('aria-busy', 'true');
		try {
			await new Promise((resolve) => window.setTimeout(resolve, 500));
			if (controller.signal.aborted || !control.isConnected) return;
			const { page, destination } = await fetchPage(link.href, { signal: controller.signal });
			if (controller.signal.aborted || !control.isConnected) return;
			const existing = new Set([...main.querySelectorAll('article.post[id]')].map((article) => article.id));
			const articles = [...page.querySelectorAll('#main > article.post')]
				.filter((article) => !existing.has(article.id))
				.map((article) => document.importNode(article, true));
			if (!articles.length) throw new Error('没有获取到新文章，请重试。');
			const fragment = document.createDocumentFragment();
			articles.forEach((article) => fragment.append(article));
			main.insertBefore(fragment, control);
			const loaded = Math.min(2, (Number(control.dataset.p1LoadCount) || 0) + 1);
			control.dataset.p1LoadCount = String(loaded);
			const nextLink = page.querySelector('.p1-load-more-button[href]');
			if (loaded >= 2 || !nextLink) {
				link.hidden = true;
				const archive = control.querySelector('.p1-load-more-archive');
				if (archive) archive.hidden = false;
			} else {
				link.href = new URL(nextLink.getAttribute('href'), destination).href;
			}
			document.dispatchEvent(new CustomEvent('p1:posts-updated', { detail: { root: main } }));
		} catch (error) {
			if (error.name !== 'AbortError' && control.isConnected && message) {
				message.textContent = error.message || '加载失败，请重试。';
				message.hidden = false;
			}
		} finally {
			if (loadMoreRequest === controller) loadMoreRequest = null;
			if (control.isConnected) {
				control.classList.remove('is-loading');
				control.removeAttribute('aria-busy');
			}
		}
	}

	async function navigate(url, mode = 'push', scrollY) {
		const destination = new URL(url, location.href);
		if (!isLocalPage(destination)) { location.assign(destination.href); return; }
		loadMoreRequest?.abort();
		if (request) request.abort();
		const controller = new AbortController();
		request = controller;
		const id = ++navigationId;
		const loading = startLoading();
		if (mode === 'push') history.replaceState({ ...history.state, p1Page: true, scrollY: window.scrollY }, '', currentUrl);
		try {
			const result = await fetchPage(destination.href, { signal: controller.signal });
			await prepareAssets(result.page, result.destination);
			if (controller.signal.aborted || id !== navigationId) return;
			commitPage(result.page, result.destination, mode, scrollY);
		} catch (error) {
			if (error.name !== 'AbortError' && id === navigationId) location.assign(destination.href);
		} finally {
			if (request === controller) request = null;
			stopLoading(loading);
		}
	}

	window.p1Navigate = (url) => navigate(url);

	document.addEventListener('click', (event) => {
		const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
		if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ||
			link.target && link.target !== '_self' || link.hasAttribute('download') || link.matches('.quickbar-random-button, .comment-reply-link, #cancel-comment-reply-link, [data-no-pjax]') ||
			link.closest('[contenteditable], [data-no-pjax]')) return;
		if (link.matches('.p1-load-more-button')) {
			event.preventDefault();
			loadMore(link);
			return;
		}
		const url = new URL(link.href, location.href);
		if (!isLocalPage(url) || url.href === location.href || url.hash && url.pathname === location.pathname && url.search === location.search) return;
		event.preventDefault();
		navigate(url.href);
	});

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
		if (form.id === 'commentform') {
			event.preventDefault();
			submitComment(form);
			return;
		}
		if (form.method.toLowerCase() !== 'get' || !form.querySelector('[name="s"]')) return;
		const url = new URL(form.action, location.href);
		if (!isLocalPage(url)) return;
		for (const [key, value] of new FormData(form)) if (typeof value === 'string') url.searchParams.set(key, value);
		event.preventDefault();
		navigate(url.href);
	});

	async function submitComment(form) {
		if (form.dataset.p1Submitting) return;
		const id = ++navigationId;
		if (request) request.abort();
		loadMoreRequest?.abort();
		const controller = new AbortController();
		request = controller;
		form.dataset.p1Submitting = '1';
		const submit = form.querySelector('[type="submit"]');
		if (submit) submit.disabled = true;
		form.querySelector('.p1-comment-error')?.remove();
		const loading = startLoading();
		try {
			const publicIP = await window.p1PublicIP?.get();
			if (controller.signal.aborted || id !== navigationId || !form.isConnected) return;
			const body = new FormData(form);
			if (publicIP) body.set('p1_public_ip', publicIP);
			const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
			const html = await response.text();
			if (controller.signal.aborted || id !== navigationId || !form.isConnected) return;
			const page = new DOMParser().parseFromString(html, 'text/html');
			if (!response.ok || !isThemePage(page) || !isLocalPage(new URL(response.url))) {
				const message = page.querySelector('.wp-die-message')?.textContent?.trim();
				throw new Error(message || '评论提交失败，请稍后重试。');
			}
			await prepareAssets(page, response.url);
			if (controller.signal.aborted || id !== navigationId || !form.isConnected) return;
			commitPage(page, response.url.includes('#') ? response.url : `${response.url}#comments`, 'replace', undefined, { recordView: false });
			window.p1Toast?.show('评论成功', { type: 'success', detail: '感谢你留下评论' });
		} catch (error) {
			if (error.name === 'AbortError' || id !== navigationId || !form.isConnected) return;
			const message = document.createElement('p');
			message.className = 'p1-comment-error';
			message.setAttribute('role', 'alert');
			message.textContent = error instanceof TypeError ? '评论提交失败，请检查网络后重试。' : error.message || '评论提交失败，请稍后重试。';
			form.prepend(message);
		} finally {
			if (request === controller) request = null;
			delete form.dataset.p1Submitting;
			if (submit) submit.disabled = false;
			stopLoading(loading);
		}
	}

	window.addEventListener('popstate', (event) => {
		if (location.href !== currentUrl) navigate(location.href, 'pop', event.state?.scrollY);
	});
})();
;

// Article outline: reveal H3 children only inside the current H2 section.
(() => {
	'use strict';
	let dispose;
	function mount() {
		dispose?.();
		dispose = null;
		if (!document.body.classList.contains('single-post')) return;
		const entry = document.querySelector('.single-post-body > .entry--reading');
		if (!entry) return;
		const headings = [...entry.querySelectorAll('h2')].filter((heading) => heading.textContent.trim() && !heading.closest('[hidden], [aria-hidden="true"]'));
		if (!headings.length) return;
		const controller = new AbortController();
		const { signal } = controller;
		const wide = window.matchMedia('(min-width: 84rem)');
		const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
		const nav = document.createElement('nav');
		nav.className = 'p1-article-toc';
		const scrollControls = document.querySelector('.p1-quickbar--article .quickbar-scroll-controls');
		nav.setAttribute('aria-label', '文章目录');
		nav.style.setProperty('--p1-toc-count', String(headings.length));
		const track = document.createElement('div');
		track.className = 'p1-toc-track';
		track.setAttribute('role', 'progressbar');
		track.setAttribute('aria-label', '文章阅读进度');
		track.setAttribute('aria-valuemin', '0');
		track.setAttribute('aria-valuemax', '100');
		track.setAttribute('aria-valuenow', '0');
		const ticks = Array.from({ length: 24 }, () => {
			const tick = document.createElement('span');
			tick.className = 'p1-toc-tick';
			tick.setAttribute('aria-hidden', 'true');
			track.append(tick);
			return tick;
		});
		const list = document.createElement('ol');
		list.className = 'p1-toc-list';
		const postKey = entry.closest('article')?.id || 'article';
		const records = [];
		const sections = [];
		let parent = null;
		let subheadingIndex = 0;
		const outline = [...entry.querySelectorAll('h2, h3')].filter((heading) => heading.textContent.trim() && !heading.closest('[hidden], [aria-hidden="true"]'));
		outline.forEach((heading) => {
			const isSection = heading.tagName === 'H2';
			if (!isSection && !parent) return;
			const index = isSection ? sections.length : subheadingIndex++;
			if (!heading.id || document.getElementById(heading.id) !== heading) {
				const base = `p1-${isSection ? 'section' : 'subsection'}-${postKey}-${index + 1}`;
				let id = base;
				let suffix = 1;
				while (document.getElementById(id)) id = `${base}-${suffix++}`;
				heading.id = id;
			}
			const item = document.createElement('li');
			const link = document.createElement('a');
			link.href = `#${encodeURIComponent(heading.id)}`;
			link.textContent = heading.textContent.trim();
			link.addEventListener('click', (event) => {
				if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
				event.preventDefault();
				window.scrollTo({ top: Math.max(0, heading.getBoundingClientRect().top + window.scrollY - readingOffset()), behavior: reducedMotion.matches ? 'instant' : 'smooth' });
				heading.setAttribute('tabindex', '-1');
				heading.focus({ preventScroll: true });
			}, { signal });
			item.append(link);
			if (isSection) {
				const children = document.createElement('ol');
				children.className = 'p1-toc-children';
				children.id = `p1-toc-children-${postKey}-${index + 1}`;
				children.hidden = true;
				parent = { heading, link, children, index, childCount: 0 };
				sections.push(parent);
				list.append(item);
			} else {
				item.className = 'p1-toc-subitem';
				if (!parent.childCount) {
					parent.link.parentElement.append(parent.children);
					parent.link.setAttribute('aria-controls', parent.children.id);
					parent.link.setAttribute('aria-expanded', 'false');
				}
				parent.childCount++;
				parent.children.append(item);
			}
			records.push({ heading, link, section: parent.index, isSection });
		});
		const links = records.map((record) => record.link);
		nav.append(track, list);
		document.body.append(nav);
		let frame = 0;
		let current = -1;
		let expanded = -1;
		function readingOffset() {
			const menu = document.querySelector('.primary-navigation');
			const admin = document.getElementById('wpadminbar');
			return (menu?.getBoundingClientRect().height || 0) + (admin?.getBoundingClientRect().height || 0) + 20;
		}
		function update() {
			frame = 0;
			const offset = readingOffset();
			entry.style.setProperty('--p1-toc-scroll-offset', `${offset}px`);
			scrollControls?.classList.toggle('is-below-toc', wide.matches);
			if (!wide.matches) {
				scrollControls?.style.removeProperty('--p1-scroll-controls-top');
				return;
			}
			const rect = entry.getBoundingClientRect();
			const distance = rect.height - (window.innerHeight - offset);
			const progress = distance > 0 ? Math.min(1, Math.max(0, (offset - rect.top) / distance)) : rect.bottom <= window.innerHeight ? 1 : 0;
			track.setAttribute('aria-valuenow', String(Math.round(progress * 100)));
			ticks.forEach((tick, index) => tick.classList.toggle('is-read', (index + 1) / ticks.length <= progress));
			let section = -1;
			headings.forEach((heading, index) => {
				if (heading.getBoundingClientRect().top <= offset + 2) section = index;
			});
			// Collapse all children before the first section and after the article ends.
			const nextExpanded = rect.bottom > offset + 2 ? section : -1;
			const expansionChanged = nextExpanded !== expanded;
			if (expansionChanged) {
				expanded = nextExpanded;
				sections.forEach((group) => {
					if (!group.childCount) return;
					const open = group.index === expanded;
					group.children.hidden = !open;
					group.link.setAttribute('aria-expanded', String(open));
				});
				nav.style.setProperty('--p1-toc-count', String(headings.length + (sections[expanded]?.childCount || 0)));
			}
			let active = 0;
			records.forEach((record, index) => {
				if (record.section !== Math.max(0, section)) return;
				if (record.isSection || expanded === record.section && record.heading.getBoundingClientRect().top <= offset + 2) active = index;
			});
			links.forEach((link) => {
				if (link.clientWidth && link.scrollWidth > link.clientWidth + 1) link.dataset.tooltip = link.textContent;
				else delete link.dataset.tooltip;
			});
			scrollControls?.style.setProperty('--p1-scroll-controls-top', `${nav.getBoundingClientRect().bottom + 12}px`);
			if (active === current && !expansionChanged) return;
			current = active;
			links.forEach((link, index) => {
				if (index === active) link.setAttribute('aria-current', 'location');
				else link.removeAttribute('aria-current');
			});
			const itemRect = links[active].getBoundingClientRect();
			const listRect = list.getBoundingClientRect();
			if (itemRect.top < listRect.top) list.scrollTop -= listRect.top - itemRect.top;
			else if (itemRect.bottom > listRect.bottom) list.scrollTop += itemRect.bottom - listRect.bottom;
		}
		function schedule() {
			if (!frame) frame = window.requestAnimationFrame(update);
		}
		window.addEventListener('scroll', schedule, { passive: true, signal });
		window.addEventListener('resize', schedule, { passive: true, signal });
		entry.addEventListener('load', schedule, { capture: true, signal });
		const observer = new ResizeObserver(schedule);
		observer.observe(entry);
		const menu = document.querySelector('.primary-navigation');
		if (menu) observer.observe(menu);
		document.fonts?.ready.then(() => { if (!signal.aborted) schedule(); });
		dispose = () => {
			controller.abort();
			observer.disconnect();
			window.cancelAnimationFrame(frame);
			scrollControls?.style.removeProperty('--p1-scroll-controls-top');
			scrollControls?.classList.remove('is-below-toc');
			nav.remove();
			entry.style.removeProperty('--p1-toc-scroll-offset');
		};
		update();
		if (location.hash) {
			let target;
			try { target = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch { /* Ignore malformed fragments. */ }
			if (outline.includes(target)) target.scrollIntoView({ behavior: 'instant' });
		}
	}
	document.addEventListener('p1:page-leave', () => { dispose?.(); dispose = null; });
	document.addEventListener('p1:page-ready', mount);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
	else mount();
})();
;

// Footer visitor statistics (ShanYing method, adapted for P1 PJAX).
(() => {
	'use strict';
	const endpoint = p1RuntimeConfig.views?.ajaxUrl;
	if (!endpoint) return;
	const uuid = () => {
		if (window.crypto?.randomUUID) return window.crypto.randomUUID();
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
			const n = window.crypto?.getRandomValues ? window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 : Math.floor(Math.random() * 16);
			return (c === 'x' ? n : (n & 3) | 8).toString(16);
		});
	};
	let visitor;
	try {
		visitor = localStorage.getItem('p1-visitor-id');
		if (!/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(visitor || '')) {
			visitor = uuid();
			localStorage.setItem('p1-visitor-id', visitor);
		}
	} catch { visitor = uuid(); }
	let sequence = Promise.resolve();
	let current = null;
	let initialTimer;
	let retryTimer;
	function render(root, data) {
		const views = root.querySelector('[data-p1-footer-views]');
		const online = root.querySelector('[data-p1-footer-online]');
		const location = root.querySelector('[data-p1-footer-location]');
		if (views && Number.isFinite(Number(data.views))) views.textContent = Math.max(0, Number(data.views)).toLocaleString('zh-CN');
		if (online && Number.isFinite(Number(data.online))) online.textContent = Math.max(0, Number(data.online)).toLocaleString('zh-CN');
		if (location) {
			const region = data.location;
			const code = typeof region?.code === 'string' ? region.code.toLowerCase() : '';
			const hasRegion = typeof region?.label === 'string' && region.label;
			location.textContent = hasRegion ? region.label : '暂未获取';
			if (hasRegion && /^[a-z]{2}$/.test(code)) {
				const flag = document.createElement('img');
				flag.className = 'p1-footer-location-flag';
				flag.src = `https://flagcdn.io/flags/4x3/${code}.svg`;
				flag.alt = '';
				flag.width = 19;
				flag.height = 14;
				flag.addEventListener('error', () => flag.remove(), { once: true });
				location.prepend(flag);
			}
		}
	}
	function ping() {
		const page = current;
		if (!page || !page.root.isConnected || document.hidden || page.inFlight) return;
		page.inFlight = true;
		sequence = sequence.catch(() => {}).then(async () => {
			if (page !== current || !page.root.isConnected || document.hidden) { page.inFlight = false; return; }
			const publicIP = await window.p1PublicIP?.get();
			if (page !== current || !page.root.isConnected || document.hidden) { page.inFlight = false; return; }
			const controller = new AbortController();
			const timer = setTimeout(() => controller.abort(), 12000);
			const event = page.event;
			try {
				const response = await fetch(endpoint, {
					method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
					body: new URLSearchParams({ action: 'p1_visitor_stats', visitor, event, p1_public_ip: publicIP || '' }),
				});
				const result = await response.json();
				if (!response.ok || !result.success || !result.data) throw new Error('Statistics unavailable');
				page.event = '';
				page.retries = 0;
				if (page === current && page.root.isConnected) render(page.root, result.data);
			} catch {
				if (page === current && page.root.isConnected) {
					const online = page.root.querySelector('[data-p1-footer-online]');
					if (online) online.textContent = '—';
					// Retry the same event ID, so a lost response cannot count a visit twice.
					if (page.event && page.retries++ < 2) retryTimer = setTimeout(ping, 15000);
				}
			} finally { clearTimeout(timer); page.inFlight = false; }
		});
	}
	function mount(event) {
		clearTimeout(initialTimer);
		clearTimeout(retryTimer);
		const root = document.querySelector('[data-p1-footer-stats]');
		current = root ? { root, event: event?.detail?.recordView === false ? '' : uuid(), inFlight: false, retries: 0 } : null;
		if (current) initialTimer = setTimeout(ping, 750);
	}
	document.addEventListener('p1:page-leave', () => {
		clearTimeout(initialTimer);
		clearTimeout(retryTimer);
		current = null;
	});
	document.addEventListener('p1:page-ready', mount);
	document.addEventListener('visibilitychange', () => { if (!document.hidden) ping(); });
	setInterval(ping, 60000);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => mount(), { once: true });
	else mount();
})();

;
// Friend avatars fall back to favicons without changing the one-line list layout.
(() => {
	'use strict';
	let cleanup = () => {};
	function mount() {
		cleanup();
		const page = document.querySelector('[data-p1-links-directory]');
		if (!page) return;
		const life = new AbortController();
		page.querySelectorAll('[data-p1-friend-image]').forEach((image) => {
			function fallback() {
				if (image.dataset.imageKind === 'avatar' && image.dataset.favicon) {
					image.dataset.imageKind = 'favicon';
					image.parentElement.classList.add('is-favicon');
					image.src = image.dataset.favicon;
				} else image.remove();
			}
			image.addEventListener('error', fallback, { signal: life.signal });
			if (image.complete && !image.naturalWidth) fallback();
		});
		cleanup = () => { life.abort(); cleanup = () => {}; };
	}
	document.addEventListener('p1:page-leave', () => cleanup());
	document.addEventListener('p1:page-ready', mount);
	mount();
})();

// Load cached public GitHub contributions when the homepage chart enters view.
(() => {
	'use strict';
	let cleanup = () => {};
	function mount() {
		cleanup();
		const root = document.querySelector('[data-p1-github-charts][data-refresh="1"]');
		if (!root) return;
		const life = new AbortController();
		let observer;
		let started = false;
		async function load() {
			if (started || life.signal.aborted) return;
			started = true;
			observer?.disconnect();
			root.setAttribute('aria-busy', 'true');
			const request = new AbortController();
			const abort = () => request.abort();
			life.signal.addEventListener('abort', abort, { once: true });
			const timer = setTimeout(abort, 15000);
			try {
				const url = new URL(root.dataset.endpoint, location.href);
				url.searchParams.set('action', 'p1_github_activity');
				const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: request.signal });
				const result = await response.json();
				if (!response.ok || !result.success || typeof result.data?.html !== 'string') throw new Error('Activity unavailable');
				if (!life.signal.aborted) { root.innerHTML = result.data.html; root.dataset.refresh = '0'; }
			} catch {
				if (!life.signal.aborted && !root.querySelector('.p1-github-bars')) {
					const message = document.createElement('p');
					message.className = 'p1-home-discovery-empty';
					message.textContent = 'GitHub 活动暂时无法获取，请稍后再来看看。';
					root.replaceChildren(message);
				}
			} finally {
				clearTimeout(timer);
				life.signal.removeEventListener('abort', abort);
				root.removeAttribute('aria-busy');
			}
		}
		if ('IntersectionObserver' in window) {
			observer = new IntersectionObserver((entries) => {
				if (entries.some((entry) => entry.isIntersecting)) load();
			}, { rootMargin: '200px' });
			observer.observe(root);
		} else load();
		cleanup = () => { observer?.disconnect(); life.abort(); cleanup = () => {}; };
	}
	document.addEventListener('p1:page-leave', () => cleanup());
	document.addEventListener('p1:page-ready', mount);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
	else mount();
})();

// Reveal navigation on upward scrolling and hide it while reading downward.
(() => {
	'use strict';
	let cleanup = () => {};
	function mount() {
		cleanup();
		const nav = document.querySelector('.primary-navigation');
		if (!nav) return;
		const life = new AbortController();
		const anchor = document.createElement('div');
		anchor.className = 'p1-nav-scroll-anchor';
		anchor.setAttribute('aria-hidden', 'true');
		nav.before(anchor);
		const searchToggle = nav.querySelector('.navigation-search-toggle');
		const searchInput = nav.querySelector('.navigation-search-input');
		const mobileSearch = window.matchMedia('(max-width: 52rem)');
		function closeSearch(restoreFocus = false) {
			nav.classList.remove('is-search-open');
			searchToggle?.setAttribute('aria-expanded', 'false');
			if (restoreFocus) searchToggle?.focus({ preventScroll: true });
		}
		if (searchToggle && searchInput) {
			nav.setAttribute('data-mobile-search', '');
			searchToggle.addEventListener('click', () => {
				if (nav.classList.contains('is-search-open')) closeSearch(true);
				else {
					nav.classList.add('is-search-open');
					searchToggle.setAttribute('aria-expanded', 'true');
					show();
					searchInput.focus({ preventScroll: true });
				}
			}, { signal: life.signal });
			nav.addEventListener('keydown', (event) => {
				if (event.key === 'Escape' && nav.classList.contains('is-search-open')) {
					event.preventDefault(); closeSearch(true);
				}
			}, { signal: life.signal });
			document.addEventListener('click', (event) => {
				if (!nav.contains(event.target)) closeSearch();
			}, { signal: life.signal });
			mobileSearch.addEventListener('change', () => closeSearch(), { signal: life.signal });
		}
		let lastY = Math.max(0, window.scrollY);
		let direction = 0;
		let distance = 0;
		let frame = 0;
		function topOffset() {
			const admin = document.getElementById('wpadminbar');
			const top = admin ? Math.max(0, admin.getBoundingClientRect().bottom) : 0;
			nav.style.setProperty('--p1-nav-top', `${top}px`);
			return top;
		}
		function show() { nav.classList.remove('is-scroll-hidden'); }
		function update() {
			frame = 0;
			const y = Math.max(0, Math.min(window.scrollY, document.documentElement.scrollHeight - window.innerHeight));
			const delta = y - lastY;
			lastY = y;
			const top = topOffset();
			const active = document.activeElement;
			const interacting = nav.querySelector(':focus-visible') || (nav.contains(active) && active.matches('input, textarea, select, [contenteditable]'));
			if (y <= 0 || anchor.getBoundingClientRect().top >= top || interacting) {
				show(); direction = 0; distance = 0;
				return;
			}
			if (Math.abs(delta) < 1) return;
			const nextDirection = delta > 0 ? 1 : -1;
			if (direction !== nextDirection) { direction = nextDirection; distance = 0; }
			distance += Math.abs(delta);
			if (direction < 0 && distance >= 4) show();
			else if (direction > 0 && distance >= 12) nav.classList.add('is-scroll-hidden');
		}
		function schedule() { if (!frame) frame = requestAnimationFrame(update); }
		window.addEventListener('scroll', schedule, { passive: true, signal: life.signal });
		window.addEventListener('resize', schedule, { passive: true, signal: life.signal });
		window.addEventListener('wheel', (event) => {
			if (!event.ctrlKey && event.deltaY < 0) { show(); direction = -1; distance = 0; }
		}, { passive: true, signal: life.signal });
		nav.addEventListener('focusin', show, { signal: life.signal });
		cleanup = () => {
			life.abort();
			cancelAnimationFrame(frame);
			anchor.remove();
			closeSearch();
			nav.removeAttribute('data-mobile-search');
			nav.classList.remove('is-scroll-hidden');
			nav.style.removeProperty('--p1-nav-top');
			cleanup = () => {};
		};
		show();
		topOffset();
	}
	document.addEventListener('p1:page-leave', () => cleanup());
	document.addEventListener('p1:page-ready', mount);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
	else mount();
})();

// The comment heading shortcut jumps directly to the input and starts a new comment.
document.addEventListener('click', (event) => {
	const link = event.target instanceof Element ? event.target.closest('[data-p1-focus-comment]') : null;
	if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
	const input = document.getElementById('comment');
	if (!input) return;
	event.preventDefault();
	const cancel = document.getElementById('cancel-comment-reply-link');
	if (cancel && cancel.getClientRects().length) cancel.click();
	input.focus({ preventScroll: true });
	input.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
});
