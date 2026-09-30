/* =====================================================================
   book3d.js — drag-to-look for the 3D study stack on the tour page
   (assets/book3d.css, markup in learnhub.php).

   Horizontal drag becomes camera yaw, vertical becomes pitch. Rather
   than reimplement the turn here, we pause the CSS animation and shift
   its clock with currentTime, so the keyframes stay the single source
   of truth and letting go simply resumes from the angle you stopped at
   — no jump, and no second copy of the motion logic to keep in step.
   Pitch is not animated at all, so that one is a plain custom property.
   ===================================================================== */
(function () {
  'use strict';
  var scene = document.getElementById('lh3d-scene');
  if (!scene) return;
  var turn = scene.querySelector('.lh3d-turn');
  if (!turn) return;

  var DUR = 26000;                            // must match .lh3d-turn
  var START_DEG = -26;                        // the keyframe's first angle
  var MIN_PITCH = -48, MAX_PITCH = 10;
  var pitch = -16, pitchStart = -16;
  var clock = 0, clockStart = 0;              // where the loop currently sits
  var dragging = false, startX = 0, startY = 0, anim = null;

  function track() {
    // Web Animations API where available. Without it the stack can still
    // be dragged, it just cannot know the angle it was already at, so the
    // drag starts from the keyframe's opening angle instead of a jump.
    return (turn.getAnimations && turn.getAnimations()[0]) || null;
  }
  function setPitch(next) {
    pitch = Math.min(MAX_PITCH, Math.max(MIN_PITCH, next));
    scene.style.setProperty('--lh3d-rx', pitch.toFixed(2) + 'deg');
  }
  function scrub(next) {
    clock = ((next % DUR) + DUR) % DUR;
    if (anim) anim.currentTime = clock;
    else turn.style.transform = 'rotateY(' + (clock / DUR * 360 + START_DEG).toFixed(2) + 'deg)';
  }
  function paused() {
    return dragging || calm.matches || scene.classList.contains('lh3d--off');
  }

  scene.addEventListener('pointerdown', function (e) {
    dragging = true;
    startX = e.clientX; startY = e.clientY;
    pitchStart = pitch;
    anim = track();
    clock = clockStart = anim ? anim.currentTime : 0;
    scene.classList.add('lh3d--grab');
    turn.style.animationPlayState = 'paused';
    try {
      // The pointer can already be gone by the time we get here (a very
      // fast flick, or a synthetic event), and capture throws if so.
      if (scene.setPointerCapture) scene.setPointerCapture(e.pointerId);
    } catch (err) { /* dragging still works via the events on the scene */ }
  });

  scene.addEventListener('pointermove', function (e) {
    if (!dragging) return;
    // 240px of drag = one full revolution, which feels right at this size.
    scrub(clockStart + ((e.clientX - startX) / 240) * DUR);
    setPitch(pitchStart - (e.clientY - startY) * 0.22);
  });

  ['pointerup', 'pointercancel'].forEach(function (type) {
    scene.addEventListener(type, function () {
      if (!dragging) return;
      dragging = false;
      scene.classList.remove('lh3d--grab');
      if (anim) anim.currentTime = clock;               // resume from that angle
      else turn.style.transform = '';
      turn.style.animationPlayState = paused() ? 'paused' : '';
    });
  });

  // Keyboard users get the same camera, in steps.
  scene.addEventListener('keydown', function (e) {
    var yaw = { ArrowLeft: -9, ArrowRight: 9 }[e.key];
    var tilt = { ArrowUp: 4, ArrowDown: -4 }[e.key];
    if (yaw === undefined && tilt === undefined) return;
    e.preventDefault();
    if (anim) clockStart = clock = anim.currentTime;
    if (yaw) scrub(clock + (yaw / 360) * DUR);
    if (tilt) setPitch(pitch + tilt);
  });

  // No spin-ups for people who asked the OS for calm…
  var calm = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  if (calm.matches) turn.style.animationPlayState = 'paused';

  // …and nothing to keep animating once the stack has scrolled away.
  if (window.IntersectionObserver) {
    new IntersectionObserver(function (rows) {
      rows.forEach(function (row) {
        scene.classList.toggle('lh3d--off', !row.isIntersecting);
        turn.style.animationPlayState = paused() ? 'paused' : '';
      });
    }).observe(scene);
  }
})();
