/**
 * Waits for Klarna to confirm a one step Express Checkout payment.
 *
 */
(function () {
  const params = window.kec_one_step_wait_params;

  if (!params || !params.poll_url || !params.fallback_url) {
    return;
  }

  const maxAttempts = parseInt(params.max_attempts, 10) || 1;
  const interval = parseInt(params.interval, 10) || 500;

  // A request that never answers must not stall the wait, so give every one of them its own deadline.
  const requestTimeout = Math.max(interval * 2, 4000);

  let attempts = 0;
  let done = false;

  /**
   * Send the customer to the given URL, once.
   *
   * @param {string} url The URL to continue to.
   * @returns {void}
   */
  function leave(url) {
    if (done) {
      return;
    }

    done = true;
    window.location.replace(url);
  }

  /**
   * Get the URL to poll for the given attempt.
   *
   * @param {number} attempt The attempt number.
   * @returns {string}
   */
  function pollUrl(attempt) {
    const separator = -1 === params.poll_url.indexOf("?") ? "?" : "&";

    return params.poll_url + separator + "kec-one-step-attempt=" + attempt;
  }

  /**
   * Ask the site whether Klarna has confirmed the payment yet.
   *
   * @returns {void}
   */
  function poll() {
    if (done) {
      return;
    }

    if (++attempts > maxAttempts) {
      leave(params.fallback_url);
      return;
    }

    const controller = "AbortController" in window ? new AbortController() : null;
    const options = { credentials: "same-origin" };
    let settled = false;
    let timer;

    if (controller) {
      options.signal = controller.signal;
    }

    // Move on to the next attempt once, whether the request answered, failed or timed out.
    const advance = () => {
      if (settled) {
        return;
      }

      settled = true;
      window.clearTimeout(timer);
      window.setTimeout(poll, interval);
    };

    const settle = () => {
      settled = true;
      window.clearTimeout(timer);
    };

    timer = window.setTimeout(() => {
      if (controller) {
        controller.abort();
      }

      advance();
    }, requestTimeout);

    fetch(pollUrl(attempts), options)
      .then((response) => response.json())
      .then((result) => {
        const data = (result && result.data) || {};

        if (data.redirect_url) {
          settle();
          leave(data.redirect_url);
          return;
        }

        // The site has stopped waiting for the confirmation, so continue to the page the customer was headed for.
        if (data.done) {
          settle();
          leave(params.fallback_url);
          return;
        }

        advance();
      })
      .catch(advance);
  }

  // The deadline the no JavaScript refresh uses, plus the grace of one timed out request.
  window.setTimeout(() => leave(params.fallback_url), maxAttempts * interval + requestTimeout);

  window.setTimeout(poll, interval);
})();
