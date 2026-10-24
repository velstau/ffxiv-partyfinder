"""Run against tests/Browser/router.php only. Requires Python Playwright + Chrome.

PARTYFINDER_TEST_URL=http://127.0.0.1:8099/ff14tools/partyfinder/ python3 tests/Browser/ui_smoke.py
The server uses fixtures; browser requests to external hosts are intercepted.
"""

import csv
import io
import json
import os
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import expect, sync_playwright

BASE = os.environ["PARTYFINDER_TEST_URL"].rstrip("/") + "/"
SHOTS = Path(os.environ.get("PARTYFINDER_SCREENSHOTS", "/tmp/partyfinder-ui"))
SHOTS.mkdir(parents=True, exist_ok=True)
SAVED = "ff_partyfinder.saved_conditions.v1"
HIDDEN = "ff_partyfinder.hidden_entries.v1"
QUERY = "?search=1&q=募集&keywords=&page_from=1&page_to=1&fixed_only=0&read_body=0"


def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(
            executable_path=os.environ.get("CHROME_BIN", "/usr/bin/google-chrome"),
            args=["--no-sandbox", "--disable-dev-shm-usage", "--no-proxy-server"],
        )
        context = browser.new_context(viewport={"width": 1440, "height": 1000})
        # Avoid contacting Lodestone/CDNs even for avatars in fixture cards.
        context.route("**/*", lambda route: route.continue_()
                      if urlparse(route.request.url).netloc == urlparse(BASE).netloc
                      else route.fulfill(status=200, content_type="image/svg+xml",
                                         body='<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44"/>'))
        page = context.new_page()
        page.set_default_timeout(10000)
        errors = []
        page.on("pageerror", lambda error: errors.append(str(error)))
        page.on("console", lambda msg: errors.append(msg.text) if msg.type == "error" else None)

        def goto(query=""):
            response = page.goto(BASE + query, wait_until="load")
            assert response.status == 200
            expect(page.locator("#saved-open")).to_be_visible()

        def open_saved():
            page.locator('#saved-open').click()
            expect(page.locator('#saved-panel')).to_be_visible()

        def open_hidden():
            page.locator('#hidden-open').click()
            expect(page.locator('#hidden-panel')).to_be_visible()

        def close_modal():
            page.keyboard.press('Escape')
            expect(page.locator('.modal.show')).to_have_count(0)
            expect(page.locator('.modal-backdrop')).to_have_count(0)

        def count(n):
            expect(page.locator(".summary > span").first).to_have_text(f"該当 {n} 件")

        def storage(key):
            return page.evaluate("key => JSON.parse(localStorage.getItem(key))", key)

        def layout(label):
            for width in [1440, 1024, 768, 390, 320]:
                page.set_viewport_size({"width": width, "height": 900})
                assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"), (label, width, page.evaluate("""() => ({width:document.documentElement.scrollWidth, nodes:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth && !e.closest('.table-scroll')).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right]).slice(0,12)})"""))
                assert page.locator("#q").evaluate("e => parseFloat(getComputedStyle(e).fontSize)") >= 16
                if width in [1440, 390]:
                    page.screenshot(path=str(SHOTS / f"{label}-{width}.png"), full_page=True)
            page.set_viewport_size({"width": 1440, "height": 1000})

        goto()
        expect(page.locator("#keywords")).to_have_value("絶 妖星")
        expect(page.locator('[name="read_body"]')).to_be_checked()
        expect(page.locator('[name="fixed_only"]')).not_to_be_checked()
        assert page.locator("#sort").count() == page.locator("#view_mode").count() == 1
        layout("initial")
        # Native keyboard operation and visible focus on selectable chips.
        role = page.locator('[name="role_filter[]"][value="D3"]')
        role.focus()
        page.keyboard.press("Space")
        expect(role).to_be_checked()
        assert role.locator("..").locator("span").evaluate("e => getComputedStyle(e).outlineStyle") == "solid"
        page.keyboard.press("Space")
        if page.locator("#advanced-filters").evaluate("e => e.open"):
            page.locator("#advanced-filters > summary").click()
        assert not page.locator("#advanced-filters").evaluate("e => e.open")

        # Saved empty strings must not become server defaults; closed details still submit.
        page.locator("#advanced-filters > summary").click()
        page.locator("#keywords").fill("")
        page.locator("#blog_lang").select_option("")
        page.locator("#page_to").fill("1")
        page.locator('[name="read_body"]').uncheck()
        open_saved()
        page.locator("#saved-name").fill("空欄を保存 <script>")
        page.locator("#saved-save").click()
        assert storage(SAVED)[0]["params"]["keywords"] == ""
        assert storage(SAVED)[0]["params"]["blog_lang"] == ""
        close_modal()
        page.locator("#keywords").fill("変更中")
        open_saved()
        with page.expect_navigation(wait_until="load"):
            page.locator(".saved-load").click()
        count(12)
        expect(page.locator("#keywords")).to_have_value("")
        expect(page.locator("#blog_lang")).to_have_value("")
        expect(page.locator('[name="read_body"]')).not_to_be_checked()
        params = parse_qs(urlparse(page.url).query, keep_blank_values=True)
        assert params["keywords"] == params["blog_lang"] == [""]
        assert params["read_body"] == ["0"]
        assert page.locator('details.matrix').first.evaluate('e => e.open')
        assert page.locator('details.matrix').first.bounding_box()['y'] < page.locator('table.recruits').first.bounding_box()['y']
        expect(page.locator('link[href*="vendor/tabler/"]')).to_have_count(1)
        expect(page.locator('script[src*="vendor/tabler/"]')).to_have_count(1)
        layout("table")

        # Controls outside the form still participate in save/submit. CSV ignores drafts.
        csv_url = page.get_by_role("link", name="CSVダウンロード").get_attribute("href")
        page.locator("#view_mode").select_option("card")
        expect(page.locator("#draft-notice")).to_be_visible()
        assert page.get_by_role("link", name="CSVダウンロード").get_attribute("href") == csv_url
        open_saved()
        page.locator("#saved-name").fill("カード")
        page.locator("#saved-save").click()
        assert storage(SAVED)[1]["params"]["view_mode"] == "card"
        close_modal()
        with page.expect_navigation(wait_until="load"):
            page.get_by_role("button", name="表示を適用").click()
        count(12)
        expect(page.locator(".results .entry")).to_have_count(12)
        expect(page.locator("#draft-notice")).to_be_hidden()
        layout("cards")

        # Exclusion affects counts and CSV; remove and clear restore server results.
        with page.expect_navigation(wait_until="load"):
            page.locator('[data-hide-id="90000001"]').click()
        count(11)
        assert storage(HIDDEN)[0]["id"] == "90000001"
        cookie = next(c for c in context.cookies() if c["name"] == "ff_partyfinder_hidden")
        assert cookie["path"] == urlparse(BASE).path.rstrip("/")
        csv_response = context.request.get(page.get_by_role("link", name="CSVダウンロード").get_attribute("href"))
        assert csv_response.status == 200
        csv_text = csv_response.body().decode("utf-8-sig")
        assert "blog/90000001/" not in csv_text
        assert len(list(csv.reader(io.StringIO(csv_text)))) == 12, (len(list(csv.reader(io.StringIO(csv_text)))), csv_text[:200])  # header + 11
        open_hidden()
        page.locator("#hidden-details > summary").click()
        with page.expect_navigation(wait_until="load"):
            page.locator("#hidden-list .saved-del").click()
        count(12)
        with page.expect_navigation(wait_until="load"):
            page.locator('[data-hide-id="90000002"]').click()
        open_hidden()
        page.once("dialog", lambda dialog: dialog.accept())
        with page.expect_navigation(wait_until="load"):
            page.locator("#hidden-clear").click()
        count(12)
        expect(page.locator("#hidden-clear")).to_be_disabled()

        # Loading state and BFCache restoration (without a real network delay).
        page.evaluate("""() => {
            const form = document.getElementById('search-form');
            form.addEventListener('submit', e => e.preventDefault(), {once: true});
            form.requestSubmit();
        }""")
        expect(page.locator("#search-form")).to_have_attribute("aria-busy", "true")
        expect(page.get_by_role("button", name="表示を適用")).to_be_disabled()
        page.evaluate("window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}))")
        expect(page.get_by_role("button", name="表示を適用")).to_be_enabled()
        expect(page.locator("#search-status")).to_have_text("")

        # Reset preserves saved conditions and exclusion data while restoring defaults.
        page.get_by_role("link", name="条件をリセット").click()
        expect(page.locator("#keywords")).to_have_value("絶 妖星")
        assert len(storage(SAVED)) == 2
        # Old saved schemas without arrays and corrupt local JSON must not break initialization.
        old = [{"id": "legacy", "name": "旧形式", "params": {"q": "募集", "keywords": "", "page_to": "1"}}]
        page.evaluate("([key, value]) => localStorage.setItem(key, value)", [SAVED, json.dumps(old)])
        page.reload(wait_until="load")
        open_saved()
        with page.expect_navigation(wait_until="load"):
            page.locator(".saved-load").click()
        count(12)
        page.evaluate("([a,b]) => {localStorage.setItem(a, '{'); localStorage.setItem(b, '{');}", [SAVED, HIDDEN])
        goto()
        expect(page.locator("#saved-count")).to_have_text("0 / 10")
        expect(page.locator("#hidden-count")).to_have_text("0 / 300")
        goto(QUERY + "&ng_keywords=固定%20絶%20零式")
        count(0)
        expect(page.get_by_role("heading", name="条件に合う募集はありません")).to_be_visible()
        layout("empty")
        assert not errors, errors

        # Search remains usable without localStorage/JavaScript.
        nojs = browser.new_context(java_script_enabled=False)
        nojs_page = nojs.new_page()
        nojs_page.goto(BASE, wait_until="load")
        nojs_page.locator("#keywords").fill("")
        nojs_page.locator("#advanced-filters > summary").click()
        nojs_page.locator("#page_to").fill("1")
        with nojs_page.expect_navigation(wait_until="load"):
            nojs_page.get_by_role("button", name="検索する").first.click()
        expect(nojs_page.locator(".summary > span").first).to_have_text("該当 12 件")
        nojs.close()
        context.close()
        browser.close()
        print("PASS: responsive layouts, keyboard/focus, save/restore, GET controls, exclusion/CSV, loading/reset, legacy storage, empty state, no-JS search; no console errors.")


if __name__ == "__main__":
    run()
