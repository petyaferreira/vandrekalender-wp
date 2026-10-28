/**
 * Past Walks — frontend view.
 *
 * The filter form works without JavaScript (a GET form with a submit
 * button). With it, a changed select submits the form straight away, the
 * button is hidden, and empty fields are left out of the URL.
 */

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.vk-past-walks__filters').forEach(initFilters);
});

/**
 * Enhance one filter form.
 *
 * @param {HTMLFormElement} form The filter form.
 */
function initFilters(form) {
  form.classList.add('is-enhanced');

  form.querySelectorAll('select').forEach(select => {
    select.addEventListener('change', () => form.requestSubmit());
  });

  form.addEventListener('submit', () => {
    form.querySelectorAll('input, select').forEach(field => {
      if (!field.value) {
        field.disabled = true;
      }
    });
  });
}
