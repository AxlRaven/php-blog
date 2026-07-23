(function () {
  const feed = document.getElementById("feed");
  const btn = document.getElementById("load-more");
  const CLIP_MAX_HEIGHT = 650;

  function assetBase() {
    const scripts = document.getElementsByTagName("script");
    for (let i = scripts.length - 1; i >= 0; i--) {
      const src = scripts[i].src || "";
      const m = src.match(/^(.*)assets\/js\/app\.js/);
      if (m) return m[1];
    }
    return "/";
  }

  function initTallImageClips(root) {
    if (!root || !root.classList.contains("feed--clip-tall-images")) return;

    root.querySelectorAll(".post-card__body img.content-thumb").forEach(function (img) {
      if (img.closest(".img-clip")) return;

      const setup = function () {
        if (img.offsetHeight <= CLIP_MAX_HEIGHT) return;

        const target = img.closest("a.js-lightbox") || img;
        const parent = target.parentNode;
        if (!parent) return;

        const clip = document.createElement("div");
        clip.className = "img-clip is-clipped";

        const viewport = document.createElement("div");
        viewport.className = "img-clip__viewport";

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "img-clip__toggle";
        toggle.textContent = "Показать полностью";

        parent.insertBefore(clip, target);
        viewport.appendChild(target);
        clip.appendChild(viewport);
        clip.appendChild(toggle);

        toggle.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          clip.classList.remove("is-clipped");
          clip.classList.add("is-expanded");
          toggle.remove();
        });
      };

      if (img.complete) {
        setup();
      } else {
        img.addEventListener("load", setup, { once: true });
      }
    });
  }

  if (feed) {
    initTallImageClips(feed);
  }

  if (feed && btn && feed.dataset.mode === "infinite") {
    const root = assetBase();

    btn.addEventListener("click", async function () {
      const next = parseInt(btn.dataset.next || "0", 10);
      if (!next) return;

      btn.disabled = true;
      btn.textContent = "Загрузка…";

      const params = new URLSearchParams({ page: String(next) });
      if (feed.dataset.tag) params.set("tag", feed.dataset.tag);

      try {
        const res = await fetch(root + "api/feed.php?" + params.toString(), {
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!data.ok) throw new Error("bad response");

        const wrap = document.createElement("div");
        wrap.innerHTML = data.html;
        while (wrap.firstChild) {
          feed.appendChild(wrap.firstChild);
        }

        if (window.Prism) {
          window.Prism.highlightAllUnder(feed);
        }

        initTallImageClips(feed);

        feed.dataset.page = String(data.page);

        if (data.has_more) {
          btn.dataset.next = String(data.page + 1);
          btn.disabled = false;
          btn.textContent = "Показать ещё";
        } else {
          btn.remove();
        }
      } catch (e) {
        btn.disabled = false;
        btn.textContent = "Попробовать снова";
      }
    });
  }

  /* ——— Lightbox ——— */
  let overlay = null;
  let activeThumb = null;

  function ensureOverlay() {
    if (overlay) return overlay;
    overlay = document.createElement("div");
    overlay.className = "lightbox";
    overlay.setAttribute("hidden", "");
    overlay.innerHTML =
      '<button type="button" class="lightbox__close" aria-label="Закрыть">×</button>' +
      '<figure class="lightbox__stage"><img class="lightbox__img" alt=""></figure>';
    document.body.appendChild(overlay);

    overlay.addEventListener("click", function (e) {
      if (e.target === overlay || e.target.classList.contains("lightbox__stage")) {
        closeLightbox();
      }
    });
    overlay.querySelector(".lightbox__close").addEventListener("click", closeLightbox);
    return overlay;
  }

  function openLightbox(anchor) {
    const full = anchor.getAttribute("data-full") || anchor.getAttribute("href");
    if (!full) return;
    const thumbImg = anchor.querySelector("img");
    activeThumb = thumbImg;

    const box = ensureOverlay();
    const big = box.querySelector(".lightbox__img");
    big.src = full;
    big.alt = (thumbImg && thumbImg.alt) || "";

    // FLIP-ish: start from thumb rect
    if (thumbImg) {
      const r = thumbImg.getBoundingClientRect();
      big.style.setProperty("--lx", r.left + r.width / 2 + "px");
      big.style.setProperty("--ly", r.top + r.height / 2 + "px");
      big.style.setProperty("--lw", r.width + "px");
      big.style.setProperty("--lh", r.height + "px");
    }

    box.removeAttribute("hidden");
    requestAnimationFrame(function () {
      box.classList.add("is-open");
      document.body.classList.add("lightbox-open");
    });
  }

  function closeLightbox() {
    if (!overlay || !overlay.classList.contains("is-open")) return;
    overlay.classList.remove("is-open");
    document.body.classList.remove("lightbox-open");
    const onEnd = function () {
      overlay.setAttribute("hidden", "");
      const big = overlay.querySelector(".lightbox__img");
      big.removeAttribute("src");
      overlay.removeEventListener("transitionend", onEnd);
    };
    overlay.addEventListener("transitionend", onEnd);
    setTimeout(onEnd, 400);
    activeThumb = null;
  }

  document.addEventListener("click", function (e) {
    const a = e.target.closest && e.target.closest("a.js-lightbox");
    if (!a) return;
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    openLightbox(a);
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeLightbox();
  });

  /* ——— Post rating ——— */
  function ratingLabel(count) {
    const n = Math.abs(count) % 100;
    const n1 = n % 10;
    if (n > 10 && n < 20) return "оценок";
    if (n1 === 1) return "оценка";
    if (n1 >= 2 && n1 <= 4) return "оценки";
    return "оценок";
  }

  function fillRatingStars(stars, value) {
    stars.forEach(function (star) {
      const n = parseInt(star.getAttribute("data-rating") || "0", 10);
      star.classList.toggle("is-filled", n <= value);
    });
  }

  document.querySelectorAll(".post-rating--interactive").forEach(function (block) {
    const stars = block.querySelectorAll(".post-rating__star[data-rating]");
    stars.forEach(function (star) {
      star.addEventListener("mouseenter", function () {
        fillRatingStars(stars, parseInt(star.getAttribute("data-rating") || "0", 10));
      });
      star.addEventListener("focus", function () {
        fillRatingStars(stars, parseInt(star.getAttribute("data-rating") || "0", 10));
      });
    });
    block.addEventListener("mouseleave", function () {
      fillRatingStars(stars, 0);
    });
  });

  document.addEventListener("click", async function (e) {
    const star = e.target.closest && e.target.closest(".post-rating__star[data-rating]");
    if (!star) return;
    const block = star.closest(".post-rating--interactive");
    if (!block || block.dataset.busy === "1") return;

    const postId = parseInt(block.getAttribute("data-post-id") || "0", 10);
    const rating = parseInt(star.getAttribute("data-rating") || "0", 10);
    if (!postId || rating < 1 || rating > 5) return;

    const message = block.querySelector(".post-rating__message");
    block.dataset.busy = "1";
    if (message) {
      message.textContent = "Сохраняем…";
      message.classList.remove("post-rating__message--error");
    }

    try {
      const res = await fetch(assetBase() + "api/rate.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ post_id: postId, rating: rating }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || "Ошибка");

      block.classList.remove("post-rating--interactive");
      block.setAttribute("data-user-rating", String(rating));
      fillRatingStars(block.querySelectorAll(".post-rating__star"), Math.round(data.average));

      const avgEl = block.querySelector(".post-rating__average");
      const countEl = block.querySelector(".post-rating__count");
      const emptyEl = block.querySelector(".post-rating__empty");
      const labelEl = block.querySelector(".post-rating__label");
      if (emptyEl) emptyEl.remove();
      if (avgEl) avgEl.textContent = Number(data.average).toFixed(1);
      if (countEl) countEl.textContent = "(" + data.count + " " + ratingLabel(data.count) + ")";
      if (labelEl) labelEl.textContent = "Рейтинг:";

      block.querySelectorAll(".post-rating__star[data-rating]").forEach(function (s) {
        const btn = s;
        const span = document.createElement("span");
        span.className = btn.className;
        span.setAttribute("aria-hidden", "true");
        span.textContent = "★";
        btn.replaceWith(span);
      });

      const thanks = document.createElement("span");
      thanks.className = "post-rating__thanks";
      thanks.textContent = "Спасибо! Ваша оценка: " + rating;
      block.appendChild(thanks);
      if (message) message.textContent = "";

      const metaValue = document.querySelector("#post-" + postId + " .post-rating-badge__value");
      const metaCount = document.querySelector("#post-" + postId + " .post-rating-badge__count");
      const metaBadge = document.querySelector("#post-" + postId + " .post-rating-badge");
      if (metaBadge && metaValue && metaCount) {
        metaValue.textContent = Number(data.average).toFixed(1);
        metaCount.textContent = "(" + data.count + ")";
      } else if (block.closest("#post-" + postId)) {
        const metaEnd = document.querySelector("#post-" + postId + " .post-card__meta-end");
        if (metaEnd && !metaEnd.querySelector(".post-rating-badge")) {
          const badge = document.createElement("span");
          badge.className = "post-rating-badge";
          badge.title = "Средний рейтинг";
          badge.innerHTML =
            '<span class="post-rating-badge__star" aria-hidden="true">★</span>' +
            '<span class="post-rating-badge__value">' + Number(data.average).toFixed(1) + "</span>" +
            '<span class="post-rating-badge__count">(' + data.count + ")</span>";
          metaEnd.insertBefore(badge, metaEnd.firstChild);
        }
      }

      const ratingValueMeta = block.querySelector('meta[itemprop="ratingValue"]');
      const ratingCountMeta = block.querySelector('meta[itemprop="ratingCount"]');
      if (ratingValueMeta) ratingValueMeta.setAttribute("content", Number(data.average).toFixed(1));
      if (ratingCountMeta) ratingCountMeta.setAttribute("content", String(data.count));
    } catch (err) {
      if (message) {
        message.textContent = err.message || "Не удалось сохранить оценку";
        message.classList.add("post-rating__message--error");
      }
    } finally {
      delete block.dataset.busy;
    }
  });
})();
