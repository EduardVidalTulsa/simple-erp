// Formulari d'alta: files dinàmiques de telèfons i correus + prova de treball (compatible amb ALTCHA). Sense dependències.
(function () {
  var f = document.getElementById('altaform');
  if (!f) return;
  var D = f.dataset, btn = f.querySelector('button[type=submit]'), st = document.getElementById('powstatus'), fld = document.getElementById('altcha');

  function addRow(boxId, name, type, max) {
    var box = document.getElementById(boxId);
    if (box.querySelectorAll('.row').length >= max) return;
    var row = document.createElement('div'); row.className = 'row';
    var i = document.createElement('input'); i.type = type; i.name = name; i.maxLength = 190; i.autocomplete = 'off';
    var b = document.createElement('button'); b.type = 'button'; b.className = 'rm'; b.textContent = D.remove;
    row.appendChild(i); row.appendChild(b); box.appendChild(row); i.focus();
  }
  f.addEventListener('click', function (e) {
    var t = e.target;
    if (t.matches('[data-add=phone]')) addRow('phones', 'telefon[]', 'tel', 3);
    else if (t.matches('[data-add=email]')) addRow('emails', 'emails[]', 'email', 3);
    else if (t.matches('.rm')) t.parentNode.parentNode.removeChild(t.parentNode);
  });

  // prova de treball: troba n tal que sha256(salt+n) == challenge (uns 25.000 càlculs de mitjana)
  function hex(buf) { var a = new Uint8Array(buf), s = ''; for (var i = 0; i < a.length; i++) s += (a[i] < 16 ? '0' : '') + a[i].toString(16); return s; }
  function sha(s) { return crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)).then(hex); }
  btn.disabled = true; st.textContent = D.verifying;
  fetch('challenge.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (c) {
    function lot(start) {
      if (start > c.maxnumber) return Promise.reject(new Error('no trobat'));
      var ps = [], end = Math.min(start + 256, c.maxnumber + 1);
      for (var n = start; n < end; n++) ps.push(sha(c.salt + n));
      return Promise.all(ps).then(function (h) {
        var i = h.indexOf(c.challenge);
        return i >= 0 ? start + i : lot(end);
      });
    }
    return lot(0).then(function (n) {
      fld.value = btoa(JSON.stringify({ algorithm: c.algorithm, challenge: c.challenge, number: n, salt: c.salt, signature: c.signature }));
    });
  }).then(function () { btn.disabled = false; st.textContent = D.ready; })
    .catch(function () { st.textContent = D.error; });
})();
