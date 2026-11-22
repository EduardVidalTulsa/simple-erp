// Botó automàtic de l'enllaç per entrar i prova de treball del formulari per entrar (el mateix protocol que l'alta, ../altaclient/challenge.php). Sense dependències.
(function () {
  var a = document.getElementById('auto');   // enllaç per entrar: es prem el botó sol
  if (a) { setTimeout(function () { a.submit(); }, 300); return; }
  var f = document.getElementById('entra');
  if (!f) return;
  var D = f.dataset, btn = f.querySelector('button[type=submit]'), st = document.getElementById('powstatus'), fld = f.querySelector('input[name=altcha]');
  function hex(buf) { var a = new Uint8Array(buf), s = ''; for (var i = 0; i < a.length; i++) s += (a[i] < 16 ? '0' : '') + a[i].toString(16); return s; }
  function sha(s) { return crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)).then(hex); }
  btn.disabled = true; st.textContent = D.verifying;
  fetch('../altaclient/challenge.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (c) {
    function lot(start) {
      if (start > c.maxnumber) return Promise.reject(new Error('no trobat'));
      var ps = [], end = Math.min(start + 256, c.maxnumber + 1);
      for (var n = start; n < end; n++) ps.push(sha(c.salt + n));
      return Promise.all(ps).then(function (h) { var i = h.indexOf(c.challenge); return i >= 0 ? start + i : lot(end); });
    }
    return lot(0).then(function (n) {
      fld.value = btoa(JSON.stringify({ algorithm: c.algorithm, challenge: c.challenge, number: n, salt: c.salt, signature: c.signature }));
    });
  }).then(function () { btn.disabled = false; st.textContent = D.ready; })
    .catch(function () { st.textContent = D.error; });
})();
