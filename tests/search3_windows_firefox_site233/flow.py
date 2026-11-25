import json
import time
from pathlib import Path

from selenium.common.exceptions import (
    ElementClickInterceptedException,
    ElementNotInteractableException,
    StaleElementReferenceException,
    WebDriverException,
)
from selenium.webdriver.common.actions.action_builder import ActionBuilder
from selenium.webdriver.common.actions.pointer_input import PointerInput
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait


def go(driver, artifacts):
    """Check the currently open public /tour-selection/ using visible controls."""
    path = Path(artifacts)
    path.mkdir(parents=True, exist_ok=True)
    report = {"passed": False, "steps": []}
    wait = WebDriverWait(driver, 20, poll_frequency=0.25,
                         ignored_exceptions=(StaleElementReferenceException,))
    four = '#quick-stars [data-action="star"][data-value="4"]'
    modal = "#modal[open]"

    def save():
        (path / "flow.json").write_text(json.dumps(report, ensure_ascii=False, indent=2),
                                        encoding="utf-8")

    def step(label, **evidence):
        report["steps"].append({"step": label, **evidence})
        save()
        print("STEP", label, flush=True)

    def check(condition, label):
        if not condition:
            raise AssertionError(label)

    def visible(selector):
        return next((e for e in driver.find_elements(By.CSS_SELECTOR, selector)
                     if e.is_displayed() and e.is_enabled()), None)

    def element(selector):
        return wait.until(lambda _: visible(selector), selector)

    def center(e):
        driver.execute_script(
            "arguments[0].scrollIntoView({block:'center',inline:'nearest',behavior:'instant'})", e)

    def click(selector):
        def attempt(_):
            e = visible(selector)
            if not e:
                return False
            center(e)
            try:
                e.click()
                return True
            except (ElementClickInterceptedException, ElementNotInteractableException):
                return False
        wait.until(attempt, "Click " + selector)

    def shot(label):
        check(driver.save_screenshot(str(path / (label + ".png"))), "Screenshot " + label)

    def rows():
        return [(e.find_element(By.CSS_SELECTOR, ".hotel-card-name").text.strip(),
                 e.find_element(By.CSS_SELECTOR, ".hotel-stars").get_attribute("aria-label"))
                for e in driver.find_elements(By.CSS_SELECTOR, ".hotel-card") if e.is_displayed()]

    def two_hotels():
        target = [("Coast Club", "4 звезды"), ("Palm Garden", "4 звезды")]
        wait.until(lambda _: sorted(rows()) == target, "Expected two named four-star hotels")
        return rows()

    def pressed():
        return element(four).get_attribute("aria-pressed") == "true"

    def price():
        return "".join(c for c in element("#modal-footer .footer-total strong").text if c.isdigit())

    try:
        check(driver.execute_script("return location.pathname") == "/tour-selection/", "Unexpected route")
        deadline = time.monotonic() + 120
        while not driver.find_elements(By.ID, "hotel-extra-fields"):
            if time.monotonic() >= deadline:
                raise AssertionError("New release marker #hotel-extra-fields unavailable")
            time.sleep(6)
            driver.refresh()
        element(".search-submit")
        stars = [e for e in driver.find_elements(By.CSS_SELECTOR, "#quick-stars button") if e.is_displayed()]
        labels = [e.text.strip() for e in stars]
        check(labels == ["Любая", "3★", "4★", "5★"], "Visible initial stars " + repr(labels))
        for selector in ("#quick-budget", "#quick-meal"):
            control = driver.find_element(By.CSS_SELECTOR, selector)
            check(not control.is_displayed(), "Initial extra field visible: " + selector)
        check(element("#hotel-filters-toggle").get_attribute("aria-expanded") == "false", "Extras open initially")
        metrics = driver.execute_script(
            "return {width:innerWidth,height:innerHeight,scrollWidth:document.documentElement.scrollWidth,"
            "maxTouchPoints:navigator.maxTouchPoints,coarse:matchMedia('(pointer:coarse)').matches}")
        check(metrics["width"] == 430 and metrics["height"] == 932, "Unexpected viewport " + repr(metrics))
        check(metrics["scrollWidth"] <= 431, "Initial horizontal overflow")
        step("initial-stars-and-collapsed-budget", stars=labels, budgetVisible=False, viewport=metrics)
        shot("01-initial")

        star = element(four)
        center(star)
        driver.execute_script("""
            window.qaStarInput=[];
            for (const kind of ['pointerdown','touchstart','click']) {
                arguments[0].addEventListener(kind,e=>window.qaStarInput.push(
                    {kind:e.type,pointer:e.pointerType||null,trusted:e.isTrusted}),{once:true,passive:true});
            }
        """, star)
        method = "WebDriver element click"
        if metrics["maxTouchPoints"] > 0:
            try:
                actions = ActionBuilder(driver, mouse=PointerInput("touch", "qa-finger"))
                actions.pointer_action.move_to(star)
                actions.pointer_action.pointer_down()
                actions.pointer_action.pause(0.1)
                actions.pointer_action.release()
                actions.perform()
                method = "WebDriver W3C touch"
            except WebDriverException as exc:
                report["touchActionError"] = type(exc).__name__
        if not pressed():
            click(four)
            if method == "WebDriver W3C touch":
                method += " then WebDriver element click"
        check(pressed(), "4-star choice did not activate")
        step("four-star-selected", inputMethod=method,
             inputEvents=driver.execute_script("return window.qaStarInput"))
        click(".search-submit")
        output = two_hotels()
        step("four-star-results", hotels=output)
        shot("02-results")
        click('[data-action="edit-search"], [data-action="top"]')
        check(pressed() and not visible("#quick-budget"), "Category or collapsed extras lost on return")
        shot("03-return")
        click("#hotel-filters-toggle")
        element("#quick-budget")
        element("#quick-meal")
        check(pressed(), "Category lost opening extra fields")
        click("#hotel-filters-toggle")
        check(not visible("#quick-budget") and pressed(), "Closing extras changed category")
        click(".search-submit")
        step("category-retained-on-return", hotels=two_hotels())

        palm = next(e for e in driver.find_elements(By.CSS_SELECTOR, ".hotel-card")
                    if e.is_displayed() and e.find_element(By.CSS_SELECTOR, ".hotel-card-name").text.strip() == "Palm Garden")
        click("#" + palm.get_attribute("id") + ' [data-action="all-offers"]')
        offers = wait.until(lambda _: [e for e in driver.find_elements(
            By.CSS_SELECTOR, '#all-offers-list [data-action="offer"]') if e.is_displayed()])
        room = offers[min(1, len(offers) - 1)]
        room_key = room.get_attribute("data-key")
        center(room)
        room.click()
        wait.until(lambda _: element(modal).get_attribute("data-offer-key") == room_key, "Chosen room reference")
        click('[data-action="start-tour-flights"]')
        details = element(".flight-comparison summary")
        if not element(".flight-comparison").get_dom_attribute("open"):
            center(details)
            details.click()
        table = element(".flight-comparison table")
        table_text = table.text
        check("багаж" in table_text.lower() and len(table.find_elements(By.TAG_NAME, "tr")) >= 5,
              "Flight comparison does not show baggage or both itineraries")
        center(table)
        shot("04-flight-comparison")
        click(".flight-compare-choose")
        click('[data-action="apply-flight"]')
        total = price()
        check(bool(total), "No total after selecting flights")
        step("room-and-flight-comparison", roomKey=room_key, comparison=table_text, total=total)
        next_buttons = ','.join('#modal-footer [data-action="' + action + '"]' for action in
                                ("confirm-tour", "start-lead", "accept-selected-price"))
        for _ in range(3):
            if visible("#prototype-lead-form"):
                break
            click(next_buttons)
            if visible("#prototype-lead-form"):
                break
        element("#prototype-lead-form")
        check(element(modal).get_attribute("data-offer-key") == room_key, "Contact lost room")
        check(price() == total, "Contact lost chosen price")
        comment = element("#prototype-lead-form input[type=tel]")
        # Exercise a nonpersonal draft without submitting or photographing the contact form.
        comment.send_keys("79990000000")
        original = comment.get_attribute("value")
        check(bool(original), "Test draft field empty")
        click("#modal-back")
        check(price() == total, "Back lost chosen price")
        check(element(modal).get_attribute("data-offer-key") == room_key, "Back lost chosen room")
        shot("05-return-from-contact")
        click(next_buttons)
        restored = element("#prototype-lead-form input[type=tel]").get_attribute("value")
        check(restored == original, "Contact draft changed after Back")
        step("contact-and-return", draftRestored=True, total=total, submitted=False)
        report["passed"] = True
        return report
    except Exception as exc:
        report["error"] = str(exc).split("Stacktrace:")[0][:450]
        raise
    finally:
        save()
