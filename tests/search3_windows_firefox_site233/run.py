"""Check one public, synthetic Search3 flow in the Windows Firefox browser."""

import json
import os
import platform
import shutil
import sys
import traceback
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.firefox.service import Service

from flow import go
from viewport import configure, configure_viewport


PUBLIC_URL = "https://anytour-search3-design-lab.fq4yjrcrmm.chatgpt.site/tour-selection/"
EVIDENCE = Path("evidence")


def run():
    EVIDENCE.mkdir(exist_ok=True)
    report = {"url": PUBLIC_URL, "os": platform.platform(), "passed": False}
    driver = None
    try:
        if platform.system() != "Windows":
            raise RuntimeError("This acceptance requires Windows")
        options = webdriver.FirefoxOptions()
        configure(options)
        firefox = Path(os.environ["ProgramFiles"]) / "Mozilla Firefox" / "firefox.exe"
        if firefox.is_file():
            options.binary_location = str(firefox)
        options.set_preference("browser.cache.disk.enable", False)
        options.set_preference("browser.cache.memory.enable", False)
        gecko = shutil.which("geckodriver")
        driver = webdriver.Firefox(service=Service(executable_path=gecko), options=options)
        report["firefox"] = driver.capabilities.get("browserVersion")
        report["geckodriver"] = driver.capabilities.get("moz:geckodriverVersion")
        driver.set_page_load_timeout(45)
        driver.get(PUBLIC_URL)
        report["viewport"] = configure_viewport(driver)
        print("Firefox and viewport:", json.dumps(report, ensure_ascii=True), flush=True)
        report["journey"] = go(driver, EVIDENCE)
        report["passed"] = True
    except Exception as exc:
        report["error"] = f"{type(exc).__name__}: {str(exc)[:1300]}"
        traceback.print_exc()
        if driver is not None:
            try:
                if not driver.execute_script("return !!document.querySelector('#prototype-lead-form input[type=tel]')?.value"):
                    driver.save_screenshot(str(EVIDENCE / "last-screen.png"))
            except Exception:
                pass
    finally:
        if driver is not None:
            try:
                driver.quit()
            except Exception:
                pass
        encoded = json.dumps(report, ensure_ascii=False, indent=2, default=str)
        (EVIDENCE / "result.json").write_text(encoded + "\n", encoding="utf-8")
        print("Result:", json.dumps(report, ensure_ascii=True), flush=True)
        if os.environ.get("GITHUB_STEP_SUMMARY"):
            with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
                summary.write("## Windows Firefox QA\n\n```json\n" + encoded + "\n```\n")
    return 0 if report["passed"] else 1


if __name__ == "__main__":
    sys.exit(run())
