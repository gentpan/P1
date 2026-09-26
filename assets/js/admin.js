/** P1 theme design: AJAX tabs, saving, media controls, and feed synchronization. */
(() => {
	'use strict';
	const root = document.querySelector('.p1-settings');
	const initialForm = root?.querySelector('#p1-settings-form');
	if (!root || !initialForm) return;
	const endpoint = initialForm.dataset.ajaxUrl;
	const pages = new Map();
	let nonce = initialForm.querySelector('[name="_wpnonce"]').value;
	let current;
	let navigation = 0;
	let request;

	function initializePage(form) {
		const page = { form, tab: form.querySelector('[name="tab"]').value, saving: false, pendingSave: null, frames: new Set() };
		const state = form.querySelector('[data-save-state]');
		const message = form.querySelector('[data-save-message]');
		const submit = form.querySelector('button[type="submit"]');
		const label = submit?.querySelector('[data-save-label]');
		const spinner = submit?.querySelector('.p1-settings-save-spinner');
		const snapshot = () => JSON.stringify([...new FormData(form)].filter(([key]) => !['_wpnonce', '_wp_http_referer', 'action'].includes(key)));
		let baseline = snapshot();
		page.setState = (kind, text) => { state.dataset.saveState = kind; message.textContent = text; };
		page.markDirty = () => {
			if (page.saving || !submit) return;
			const dirty = snapshot() !== baseline;
			page.setState(dirty ? 'dirty' : 'idle', dirty ? '有未保存的修改' : '当前页面没有未保存的修改');
		};
		form.addEventListener('input', page.markDirty);
		form.addEventListener('change', page.markDirty);
		form.addEventListener('p1:settings-change', page.markDirty);
		const palettes = [...form.querySelectorAll('.p1-palette-option')];
		const updatePalettes = () => palettes.forEach((palette) => palette.classList.toggle('is-selected', palette.querySelector('input').checked));
		palettes.forEach((palette) => palette.querySelector('input').addEventListener('change', updatePalettes));
		updatePalettes();
		form.querySelector('[data-p1-ai-preset]')?.addEventListener('change', (event) => {
			if (!event.target.value) return;
			const endpointField = form.querySelector('[name="p1[ai_endpoint]"]');
			endpointField.value = event.target.value;
			endpointField.dispatchEvent(new Event('input', { bubbles: true }));
		});
		const background = form.querySelector('#p1-site_background_preset');
		const custom = form.querySelector('.p1-site-background-field');
		const backgroundChoices = [...form.querySelectorAll('[data-p1-background-preset]')];
		if (background && custom) {
			const update = () => {
				custom.hidden = background.value !== 'custom';
				backgroundChoices.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.p1BackgroundPreset === background.value)));
			};
			background.addEventListener('change', update);
			backgroundChoices.forEach((button) => button.addEventListener('click', () => {
				background.value = button.dataset.p1BackgroundPreset;
				background.dispatchEvent(new Event('change', { bubbles: true }));
			}));
			update();
		}
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			if (!submit || page.saving || !form.reportValidity()) return;
			page.pendingSave = save();
		});
		async function save() {
			form.querySelector('[name="_wpnonce"]').value = nonce;
			const body = new FormData(form);
			body.set('action', 'p1_save_settings');
			const controls = [...form.querySelectorAll('input, select, textarea, button')].map((node) => [node, node.disabled]);
			page.saving = true;
			controls.forEach(([node]) => { node.disabled = true; });
			form.setAttribute('aria-busy', 'true');
			spinner.hidden = false;
			label.textContent = '正在保存';
			page.setState('saving', '正在保存当前页面…');
			const controller = new AbortController();
			const timer = setTimeout(() => controller.abort(), 20000);
			try {
				const response = await fetch(endpoint, { method: 'POST', body, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
				const result = await readResult(response, '保存失败，请稍后重试。');
				const settings = result.settings || {};
				form.querySelectorAll('[name]').forEach((node) => {
					const key = node.name.match(/^p1\[([^\]]+)\]$/)?.[1];
					if (!key || !Object.hasOwn(settings, key) || typeof settings[key] === 'object') return;
					if (node.type === 'checkbox') node.checked = Boolean(settings[key]);
					else if (node.type === 'radio') node.checked = node.value === String(settings[key]);
					else node.value = String(settings[key]);
				});
				if (Object.hasOwn(settings, 'ai_key_saved')) {
					const keyField = form.querySelector('[name="p1[ai_key]"]');
					if (keyField) keyField.placeholder = settings.ai_key_saved ? '已保存；留空保持不变' : '填写 API Key';
					const keyStatus = form.querySelector('[data-p1-ai-key-status]');
					if (keyStatus) keyStatus.textContent = settings.ai_key_saved ? '密钥已保存，不在页面回显。' : '尚未配置密钥。';
				}
				if (Array.isArray(settings.about_social_links)) page.syncSocialLinks?.(settings.about_social_links);
				if (typeof result.nonce === 'string') nonce = result.nonce;
				form.querySelector('[name="_wpnonce"]').value = nonce;
				updatePalettes();
				page.setState('saved', result.message || '设置已保存');
			} catch (error) {
				page.setState('error', error.name === 'AbortError' ? '保存超时，请重试；你的修改仍保留在页面中。' : error.message || '网络连接失败，请重试。');
			} finally {
				clearTimeout(timer);
				controls.forEach(([node, disabled]) => { node.disabled = disabled; });
				if (state.dataset.saveState === 'saved') baseline = snapshot();
				spinner.hidden = true;
				label.textContent = '保存设置';
				form.removeAttribute('aria-busy');
				page.saving = false;
			}
		}
		initializeHeaderImages(page);
		initializeMedia(page);
		initializeFeed(page);
		return page;
	}

	async function readResult(response, fallback) {
		let result;
		try { result = await response.json(); }
		catch { throw new Error('服务器返回异常，请稍后重试。'); }
		if (!response.ok || !result?.success || !result.data) throw new Error(result?.data?.message || fallback);
		return result.data;
	}

	function initializeHeaderImages(page) {
		const form = page.form;
		const select = form.querySelector('#p1-header-image-select');
		const remove = form.querySelector('#p1-header-image-remove');
		const input = form.querySelector('#p1-header_background_id');
		const preview = form.querySelector('#p1-header-image-preview');
		const image = form.querySelector('#p1-header-image-preview-img');
		const preset = form.querySelector('#p1-header_background_preset');
		if (!select || !remove || !input || !preview || !image || !preset) return;
		const choices = [...form.querySelectorAll('[data-p1-header-preset]')];
		let customSrc = preview.dataset.customSrc || '';
		const update = () => {
			const choice = choices.find((button) => button.dataset.p1HeaderPreset === preset.value);
			const src = preset.value === 'custom' ? customSrc : choice?.dataset.p1HeaderSrc || '';
			if (src) image.src = src;
			else image.removeAttribute('src');
			preview.hidden = !src;
			remove.hidden = !src;
			choices.forEach((button) => button.setAttribute('aria-pressed', String(button === choice)));
		};
		preset.addEventListener('change', update);
		choices.forEach((button) => button.addEventListener('click', () => {
			preset.value = button.dataset.p1HeaderPreset;
			preset.dispatchEvent(new Event('change', { bubbles: true }));
		}));
		update();
		let frame;
		select.addEventListener('click', () => {
			if (!window.wp?.media) return;
			if (!frame) {
				frame = window.wp.media({ title: '选择页头背景图片', button: { text: '使用这张图片' }, library: { type: 'image' }, multiple: false });
				page.frames.add(frame);
				frame.on('select', () => {
					const attachment = frame.state().get('selection').first()?.toJSON();
					if (!attachment?.id || !attachment.url) return;
					input.value = String(attachment.id);
					customSrc = attachment.sizes?.medium_large?.url || attachment.sizes?.large?.url || attachment.url;
					preset.value = 'custom';
					preset.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}
			frame.open();
		});
		remove.addEventListener('click', () => {
			input.value = '0';
			customSrc = '';
			preset.value = 'none';
			preset.dispatchEvent(new Event('change', { bubbles: true }));
		});
	}

	function initializeMedia(page) {
		const form = page.form;
		const rows = form.querySelector('#p1-about-social-rows');
		const template = form.querySelector('#p1-about-social-template');
		const add = form.querySelector('#p1-about-social-add');
		if (rows && template && add) {
			const updateIcon = (row) => {
				const input = row.querySelector('.p1-social-icon');
				row.querySelector('.p1-social-icon-preview i').className = socialIconClass(input.value) || 'fa-solid fa-link';
			};
			const createRow = (index) => {
				const row = template.content.firstElementChild.cloneNode(true);
				row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace('__INDEX__', String(index)); });
				return row;
			};
			rows.querySelectorAll('.p1-about-social-setting').forEach(updateIcon);
			rows.addEventListener('input', (event) => {
				if (event.target.matches('.p1-social-icon')) updateIcon(event.target.closest('.p1-about-social-setting'));
			});
			page.syncSocialLinks = (links) => {
				rows.replaceChildren(...links.map((link, index) => {
					const row = createRow(index);
					row.querySelector('.p1-social-name').value = link.label || '';
					row.querySelector('.p1-social-icon').value = link.icon || '';
					row.querySelector('.p1-social-url').value = link.url || '';
					updateIcon(row);
					return row;
				}));
			};
			let nextIndex = [...rows.querySelectorAll('input[name*="about_social_links"]')]
				.map((input) => Number(input.name.match(/about_social_links\]\[(\d+)\]/)?.[1] || -1))
				.reduce((largest, index) => Math.max(largest, index), -1) + 1;
			add.addEventListener('click', () => {
				if (rows.children.length >= 12) return;
				const row = createRow(nextIndex);
				nextIndex++;
				rows.append(row);
				row.querySelector('input[type="text"]')?.focus();
				page.markDirty();
			});
		}
		let frame;
		let selectedField;
		form.addEventListener('click', (event) => {
			const removeRow = event.target.closest('[data-about-row-remove]');
			if (removeRow) { removeRow.closest('.p1-about-social-setting')?.remove(); page.markDirty(); return; }
			const button = event.target.closest('[data-about-image-select], [data-about-image-remove]');
			const field = button?.closest('.p1-about-image-field');
			if (!field) return;
			const input = field.querySelector('[data-about-image-id]');
			const preview = field.querySelector('[data-about-image-preview]');
			const remove = field.querySelector('[data-about-image-remove]');
			if (button.matches('[data-about-image-remove]')) {
				input.value = '0'; preview.removeAttribute('src'); preview.hidden = true; remove.hidden = true;
				input.dispatchEvent(new Event('change', { bubbles: true }));
				return;
			}
			if (!window.wp?.media) return;
			selectedField = field;
			if (!frame) {
				frame = window.wp.media({ title: '选择图片', button: { text: '使用这张图片' }, library: { type: 'image' }, multiple: false });
				page.frames.add(frame);
				frame.on('select', () => {
					const image = frame.state().get('selection').first()?.toJSON();
					if (!image?.id || !image.url || !selectedField?.isConnected) return;
					const fieldInput = selectedField.querySelector('[data-about-image-id]');
					const fieldPreview = selectedField.querySelector('[data-about-image-preview]');
					fieldInput.value = String(image.id);
					fieldPreview.src = image.sizes?.medium?.url || image.url;
					fieldPreview.hidden = false;
					selectedField.querySelector('[data-about-image-remove]').hidden = false;
					fieldInput.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}
			frame.open();
		});
	}

	function socialIconClass(value) {
		if (value.length > 512) return '';
		if (value.includes('<')) {
			const match = value.match(/^\s*<i\b[^>]*\bclass\s*=\s*(["'])(.*?)\1[^>]*>\s*<\/i>\s*$/is);
			if (!match) return '';
			value = match[2];
		}
		const aliases = { fa: 'fa-solid', fas: 'fa-solid', far: 'fa-regular', fal: 'fa-light', fat: 'fa-thin', fad: 'fa-duotone', fab: 'fa-brands' };
		const classes = value.trim().split(/\s+/).filter(Boolean).map((name) => aliases[name] || name);
		const styles = ['fa-solid', 'fa-regular', 'fa-light', 'fa-thin', 'fa-duotone', 'fa-brands'];
		if (!classes.length || classes.length > 8 || classes.some((name) => !/^fa-[a-z0-9-]+$/.test(name)) || !classes.some((name) => !styles.includes(name))) return '';
		if (!classes.some((name) => styles.includes(name))) classes.unshift('fa-solid');
		return [...new Set(classes)].join(' ');
	}

	function initializeFeed(page) {
		const button = page.form.querySelector('#p1-feed-refresh');
		const status = page.form.querySelector('#p1-feed-refresh-status');
		if (!button || !status) return;
		button.addEventListener('click', async () => {
			button.disabled = true; status.textContent = '正在启动同步…';
			const controller = new AbortController();
			const timer = setTimeout(() => controller.abort(), 20000);
			try {
				const response = await fetch(button.dataset.endpoint, {
					method: 'POST', credentials: 'same-origin', signal: controller.signal,
					body: new URLSearchParams({ action: 'p1_feed_refresh', _ajax_nonce: button.dataset.nonce }),
				});
				const result = await readResult(response, '同步未启动。');
				status.textContent = result.message || '已开始同步。';
			} catch { status.textContent = '请求失败，请稍后重试。'; }
			finally { clearTimeout(timer); button.disabled = false; }
		});
	}

	const urlFor = (tab) => {
		const url = new URL(location.href);
		url.searchParams.set('page', 'p1-settings'); url.searchParams.set('tab', tab);
		url.searchParams.delete('settings-updated'); url.hash = '';
		return url;
	};
	current = initializePage(initialForm);
	pages.set(current.tab, current);
	let currentUrl = urlFor(current.tab).href;
	history.replaceState({ ...history.state, p1Settings: current.tab }, '', currentUrl);

	async function navigate(url, mode = 'push') {
		const tab = url.searchParams.get('tab') || 'appearance';
		if (![...root.querySelectorAll('.p1-settings-nav a')].some((link) => new URL(link.href).searchParams.get('tab') === tab)) return;
		const id = ++navigation;
		request?.abort();
		if (current.saving) {
			await current.pendingSave;
			if (id !== navigation) return;
		}
		if (tab === current.tab) {
			if (current.navigationStatus) {
				current.setState(current.navigationStatus.state, current.navigationStatus.message);
				delete current.navigationStatus;
			}
			root.querySelectorAll('.is-loading').forEach((node) => node.classList.remove('is-loading'));
			root.removeAttribute('aria-busy');
			request = null;
			if (mode === 'pop') currentUrl = url.href;
			return;
		}
		const old = current;
		const oldState = old.form.querySelector('[data-save-state]');
		const oldMessage = old.form.querySelector('[data-save-message]');
		const previousStatus = old.navigationStatus || { state: oldState.dataset.saveState, message: oldMessage.textContent };
		old.navigationStatus = previousStatus;
		const link = [...old.form.querySelectorAll('.p1-settings-nav a')].find((node) => new URL(node.href).searchParams.get('tab') === tab);
		link?.classList.add('is-loading');
		root.setAttribute('aria-busy', 'true');
		old.setState('loading', `正在加载${link?.textContent.trim() || '设置'}…`);
		const controller = new AbortController();
		request = controller;
		const timer = setTimeout(() => controller.abort(), 15000);
		try {
			let next = pages.get(tab);
			if (!next) {
				const response = await fetch(endpoint, {
					method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
					body: new URLSearchParams({ action: 'p1_settings_tab', tab, _wpnonce: nonce }),
				});
				const result = await readResult(response, '无法加载设置，请稍后重试。');
				if (id !== navigation) return;
				if (result.tab !== tab || typeof result.html !== 'string') throw new Error('返回的设置页面无效。');
				const documentFragment = new DOMParser().parseFromString(result.html, 'text/html');
				const returnedForm = documentFragment.querySelector('#p1-settings-form');
				if (!returnedForm || returnedForm.querySelector('[name="tab"]')?.value !== tab) throw new Error('设置内容不完整，请重试。');
				if (typeof result.nonce === 'string') nonce = result.nonce;
				next = initializePage(document.importNode(returnedForm, true));
				pages.set(tab, next);
			}
			if (old.saving) await old.pendingSave;
			if (id !== navigation) return;
			if (oldState.dataset.saveState === 'loading') old.setState(previousStatus.state, previousStatus.message);
			delete old.navigationStatus;
			for (const frame of old.frames) frame.close();
			next.form.querySelector('[name="_wpnonce"]').value = nonce;
			old.form.replaceWith(next.form);
			current = next;
			currentUrl = url.href;
			if (mode === 'push') history.pushState({ ...history.state, p1Settings: tab }, '', currentUrl);
			root.querySelector('.notice-success')?.remove();
			const top = Math.max(0, root.getBoundingClientRect().top + window.scrollY - 48);
			window.scrollTo({ top, behavior: 'instant' });
		} catch (error) {
			if (id !== navigation) return;
			delete old.navigationStatus;
			old.setState('error', error.name === 'AbortError' ? '加载超时，请再次点击标签重试。' : error.message || '加载失败，请重试。');
			if (mode === 'pop') history.replaceState({ ...history.state, p1Settings: old.tab }, '', currentUrl);
		} finally {
			clearTimeout(timer);
			link?.classList.remove('is-loading');
			if (id === navigation) { request = null; root.removeAttribute('aria-busy'); }
		}
	}
	root.addEventListener('click', (event) => {
		const link = event.target.closest('.p1-settings-nav a');
		if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		const url = new URL(link.href);
		if (url.origin !== location.origin) return;
		event.preventDefault();
		void navigate(url);
	});
	window.addEventListener('popstate', () => {
		const url = new URL(location.href);
		if (url.searchParams.get('page') === 'p1-settings') void navigate(url, 'pop');
	});
})();

/** Theme-native Passkey enrollment and login; no WordPress plugin is required. */
(() => {
	'use strict';
	const root = document.querySelector('[data-p1-passkey-profile], [data-p1-passkey-login]');
	if (!root) return;
	const status = root.querySelector('[data-p1-passkey-status]');
	const supported = window.isSecureContext && window.PublicKeyCredential && navigator.credentials;
	let busy = false;
	const message = (text, error = false) => { status.textContent = text; status.dataset.error = String(error); };
	const controls = () => [...root.querySelectorAll('button')];
	const setBusy = (value) => { busy = value; controls().forEach((button) => { button.disabled = value || (!supported && !button.hasAttribute('data-p1-passkey-remove')); }); };
	const decode = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4)), (char) => char.charCodeAt(0));
	const encode = (value) => {
		let text = '';
		for (const byte of new Uint8Array(value)) text += String.fromCharCode(byte);
		return btoa(text).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
	};
	const prepare = (options) => {
		options.challenge = decode(options.challenge);
		if (options.user) options.user.id = decode(options.user.id);
		for (const list of ['excludeCredentials', 'allowCredentials']) {
			if (options[list]) options[list].forEach((item) => { item.id = decode(item.id); });
		}
		return options;
	};
	const serialize = (credential) => {
		const response = { clientDataJSON: encode(credential.response.clientDataJSON) };
		for (const key of ['attestationObject', 'authenticatorData', 'signature', 'userHandle']) {
			if (credential.response[key]) response[key] = encode(credential.response[key]);
		}
		return JSON.stringify({ type: credential.type, id: encode(credential.rawId), response });
	};
	const request = async (operation, fields = {}) => {
		const controller = new AbortController();
		const timer = setTimeout(() => controller.abort(), 20000);
		try {
			const response = await fetch(root.dataset.endpoint, {
				method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
				body: new URLSearchParams({ action: 'p1_passkey', operation, nonce: root.dataset.nonce || '', ...fields })
			});
			const result = await response.json();
			if (!result.success) throw new Error(result.data?.message || '操作失败，请稍后重试。');
			return result.data;
		} finally { clearTimeout(timer); }
	};
	const addRow = (data) => {
		const row = document.createElement('li');
		const name = document.createElement('strong');
		name.textContent = data.name;
		const date = document.createElement('span');
		date.textContent = `添加于 ${data.created} · 尚未使用`;
		const remove = document.createElement('button');
		remove.type = 'button'; remove.className = 'button'; remove.dataset.p1PasskeyRemove = data.key; remove.textContent = '删除';
		row.append(name, date, remove);
		root.querySelector('[data-p1-passkey-list]').append(row);
		root.querySelector('[data-p1-passkey-empty]').hidden = true;
	};
	setBusy(false);
	if (!supported) {
		message(window.isSecureContext ? '此浏览器暂不支持通行密钥，请使用密码登录。' : '通行密钥需要可信的 HTTPS 连接，请先启用站点 HTTPS。');
	}
	root.addEventListener('click', async (event) => {
		const button = event.target.closest('button');
		if (!button || busy || (!supported && !button.hasAttribute('data-p1-passkey-remove'))) return;
		setBusy(true);
		try {
			if (button.hasAttribute('data-p1-passkey-remove')) {
				message('正在删除通行密钥…');
				await request('remove', { key: button.dataset.p1PasskeyRemove });
				button.closest('li').remove();
				root.querySelector('[data-p1-passkey-empty]').hidden = !!root.querySelector('[data-p1-passkey-remove]');
				message('通行密钥已删除。');
			} else if (button.hasAttribute('data-p1-passkey-add')) {
				message('请按设备提示验证身份并添加通行密钥…');
				const data = await request('register_options');
				const credential = await navigator.credentials.create({ publicKey: prepare(data.options) });
				if (!credential) throw new Error('未添加通行密钥。');
				const saved = await request('register_finish', { token: data.token, credential: serialize(credential), name: root.querySelector('#p1-passkey-name').value.trim() });
				addRow(saved);
				root.querySelector('#p1-passkey-name').value = '';
				message('通行密钥已添加，下次可以直接使用它登录。');
			} else if (button.hasAttribute('data-p1-passkey-signin')) {
				message('请使用指纹、面容、设备 PIN 或安全密钥验证…');
				const data = await request('login_options');
				const credential = await navigator.credentials.get({ publicKey: prepare(data.options) });
				if (!credential) throw new Error('未完成身份验证。');
				const form = document.querySelector('#loginform');
				const result = await request('login_finish', { token: data.token, credential: serialize(credential), remember: String(!!form?.querySelector('#rememberme')?.checked), redirect: form?.querySelector('[name="redirect_to"]')?.value || '' });
				message('登录成功，正在跳转…');
				location.assign(result.redirect);
			}
		} catch (error) {
			const text = error.name === 'NotAllowedError' ? '操作已取消或超时，可以重新尝试。' : error.name === 'InvalidStateError' ? '这个通行密钥已添加，请使用其他设备或密钥。' : error.name === 'SecurityError' ? '站点域名或 HTTPS 配置不正确，请检查后重试。' : error.name === 'AbortError' ? '请求超时，请稍后重试。' : error.message || '操作未完成，请稍后重试。';
			message(text, true);
		} finally { setBusy(false); }
	});
})();


/** Writing tools share the theme's admin bundle with settings and Passkey. */
(() => {
	'use strict';
	const config = window.p1Writing;
	if (!config) return;
	const ready = window.wp?.domReady || ((callback) => {
		if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, { once: true });
		else callback();
	});
	ready(() => {
		if (config.disableAutosave && !config.blockEditor) {
			// Keep heartbeat post locks and the native, manually requested preview.
			window.jQuery?.(() => {
				window.wp?.autosave?.server?.suspend();
				window.wp?.autosave?.local?.suspend();
				window.jQuery(document).on('heartbeat-send.p1-writing', (_event, data) => { delete data.wp_autosave; });
			});
		}
		const root = document.querySelector('[data-p1-ai-editor]');
		if (!root) return;
		const generate = root.querySelector('[data-p1-ai-generate]');
		const apply = root.querySelector('[data-p1-ai-apply]');
		const preview = root.querySelector('[data-p1-ai-preview]');
		const text = root.querySelector('textarea');
		const status = root.querySelector('[data-p1-ai-status]');
		let pending = false;
		let generated = '';
		function editorAPI() {
			try {
				const wp = config.blockEditor ? window.wp : (window.parent !== window ? window.parent.wp : null);
				const editor = wp?.data?.select('core/editor');
				return editor?.getCurrentPostId?.() ? { wp, editor } : null;
			} catch { return null; }
		}
		function currentArticle() {
			const api = editorAPI();
			if (api) return { title: api.editor.getEditedPostAttribute('title') || '', content: api.editor.getEditedPostContent() || '' };
			const visual = window.tinymce?.get('content');
			return {
				title: document.querySelector('#title')?.value || '',
				content: visual && !visual.isHidden() ? visual.getContent() : document.querySelector('#content')?.value || '',
			};
		}
		generate.addEventListener('click', async () => {
			if (pending) return;
			const article = currentArticle();
			if (!article.content.trim()) { status.textContent = '请先填写文章正文。'; return; }
			pending = true;
			generate.disabled = true;
			apply.disabled = true;
			generate.textContent = '正在生成…';
			status.textContent = '正在整理当前文章，请稍候…';
			const controller = new AbortController();
			const timer = setTimeout(() => controller.abort(), 75000);
			try {
				const response = await fetch(config.endpoint, {
					method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
					body: new URLSearchParams({ action: 'p1_ai_summary', nonce: config.nonce, post_id: root.dataset.postId, ...article }),
				});
				const result = await response.json();
				if (!response.ok || !result.success || typeof result.data?.summary !== 'string') throw new Error(result.data?.message || '生成失败，请稍后重试。');
				generated = result.data.summary;
				text.value = generated;
				preview.hidden = false;
				status.textContent = '摘要已生成，可以修改后应用。';
			} catch (error) {
				status.textContent = error.name === 'AbortError' ? '生成超时，请稍后重试。' : error.message || '连接失败，请稍后重试。';
			} finally {
				clearTimeout(timer);
				pending = false;
				generate.disabled = false;
				apply.disabled = false;
				generate.textContent = '重新生成摘要';
			}
		});
		apply.addEventListener('click', () => {
			const summary = text.value.trim();
			if (!summary || !generated) { status.textContent = '请先生成并填写摘要。'; return; }
			const api = editorAPI();
			if (api) {
				const meta = api.editor.getEditedPostAttribute('meta') || {};
				api.wp.data.dispatch('core/editor').editPost({ excerpt: summary, meta: { ...meta, _p1_ai_summary: summary } });
			} else {
				let excerpt = document.querySelector('#excerpt, [name="excerpt"]');
				if (!excerpt) {
					const postForm = document.querySelector('form#post');
					if (!postForm) { status.textContent = '没有找到文章表单，请刷新页面后重试。'; return; }
					excerpt = document.createElement('input'); excerpt.type = 'hidden'; excerpt.name = 'excerpt'; postForm.append(excerpt);
				}
				excerpt.value = summary;
				excerpt.dispatchEvent(new Event('input', { bubbles: true }));
				excerpt.dispatchEvent(new Event('change', { bubbles: true }));
			}
			root.querySelector('[name="p1_ai_summary_saved"]').value = summary;
			status.textContent = '已应用到文章摘要，请点击保存或更新文章。';
		});
	});
})();
