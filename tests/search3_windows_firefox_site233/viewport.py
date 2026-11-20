"""Use the Firefox RDM shortcut in a headed Windows Firefox session."""
import subprocess
import time


def configure(options, width=430, height=932):
    """Apply the saved preferences read by Firefox's Responsive Design Mode."""
    for key, value in {
        "devtools.responsive.viewport.width": width,
        "devtools.responsive.viewport.height": height,
        "devtools.responsive.touchSimulation.enabled": True,
    }.items():
        options.set_preference(key, value)


def _enable_rdm(driver, width=430, height=932):
    """Return verified page dimensions and touch points after the native shortcut.

    Call with a public page containing a mobile viewport meta tag already loaded.
    Start Firefox in headed mode. Never resize the plain browser to the target
    dimensions before calling. An exception means RDM proof was not obtained.
    """
    js = """return {width: innerWidth, height: innerHeight,
        screenWidth: screen.width, screenHeight: screen.height,
        touchPoints: navigator.maxTouchPoints,
        coarsePointer: matchMedia('(pointer: coarse)').matches};"""
    before = driver.execute_script(js)
    if (before["width"], before["height"]) == (width, height):
        raise RuntimeError(f"Cannot distinguish browser viewport from RDM: {before}")
    pid = int(driver.capabilities["moz:processID"])
    command = (
        "$w = New-Object -ComObject WScript.Shell; "
        f"if (-not $w.AppActivate({pid})) {{ throw 'Firefox window unavailable' }}; "
        "Start-Sleep -Milliseconds 250; $w.SendKeys('^+m')"
    )
    subprocess.run(["powershell.exe", "-NoProfile", "-NonInteractive", "-Command", command],
                   check=True, timeout=15)
    last = None
    deadline = time.monotonic() + 20
    while time.monotonic() < deadline:
        try:
            last = driver.execute_script(js)
            if ((last["width"], last["height"]) == (width, height)
                    and last["touchPoints"] > 0):
                return {"mode": "Firefox Responsive Design Mode (Windows Ctrl+Shift+M)",
                        "before": before, "after": last}
        except Exception as err:
            last = str(err)
        time.sleep(0.25)
    raise RuntimeError(f"Firefox RDM size/touch unverified: before={before}; after={last}")


def configure_viewport(driver, width=430, height=932):
    """Verify the real responsive screen, or report a labelled ordinary viewport."""
    try:
        proof = _enable_rdm(driver, width, height)
        after = proof["after"]
        return {**proof, "width": after["width"], "height": after["height"],
                "touch": after["touchPoints"], "rdmVerified": True,
                "viewportVerified": True}
    except Exception as err:
        failure = str(err)
    # Start a fresh tab to guarantee any partially activated RDM is excluded.
    old_tab = driver.current_window_handle
    url = driver.current_url
    driver.switch_to.new_window("tab")
    new_tab = driver.current_window_handle
    driver.get(url)
    driver.switch_to.window(old_tab)
    driver.close()
    driver.switch_to.window(new_tab)
    if driver.current_url != url:
        driver.get(url)
    measure = """return {width: innerWidth, height: innerHeight,
        x: outerWidth-innerWidth, y: outerHeight-innerHeight,
        touch: navigator.maxTouchPoints};"""
    for _ in range(3):
        metrics = driver.execute_script(measure)
        if (metrics["width"], metrics["height"]) == (width, height):
            break
        driver.set_window_rect(x=0, y=0, width=width+metrics["x"],
                               height=height+metrics["y"])
        time.sleep(0.3)
    actual = driver.execute_script(measure)
    return {"mode": "Windows Firefox ordinary desktop window",
            "width": actual["width"], "height": actual["height"], "touch": actual["touch"],
            "rdmVerified": False,
            "viewportVerified": (actual["width"], actual["height"]) == (width, height),
            "rdmError": failure}
