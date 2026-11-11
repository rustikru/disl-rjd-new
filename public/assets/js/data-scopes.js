;(function () {
  var search = document.getElementById('scopeSearch')
  if (search) search.addEventListener('input', function () {
    var value = search.value.trim().toLowerCase()
    document.querySelectorAll('[data-scope-row]').forEach(function (row) {
      row.hidden = !row.textContent.toLowerCase().includes(value)
    })
  })
  var rules = document.getElementById('scopeRules')
  if (!rules) return
  function initSearch(row) {
    row.querySelectorAll('input[list]').forEach(function (input) {
      var list = document.getElementById(input.getAttribute('list'))
      if (!list) return
      var items = Array.from(list.options).map(function (option) {
        return { value: option.value, label: option.textContent }
      })
      input.removeAttribute('list')
      var menu = document.createElement('div')
      menu.className = 'scope-search-options'
      menu.hidden = true
      input.parentNode.appendChild(menu)
      function search() {
        var query = input.value.trim().toLocaleLowerCase('ru').replace(/ё/g, 'е')
        menu.replaceChildren()
        items.filter(function (item) {
          return (item.value + ' ' + item.label).toLocaleLowerCase('ru').replace(/ё/g, 'е').includes(query)
        }).slice(0, 40).forEach(function (item) {
          var button = document.createElement('button')
          button.type = 'button'
          button.textContent = item.label + ' — ' + item.value
          button.addEventListener('mousedown', function (event) { event.preventDefault() })
          button.addEventListener('click', function () {
            input.value = item.value
            input.title = item.label
            menu.hidden = true
          })
          menu.appendChild(button)
        })
        menu.hidden = !menu.children.length
      }
      input.addEventListener('input', search)
      input.addEventListener('focus', search)
      input.addEventListener('blur', function () { menu.hidden = true })
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') menu.hidden = true
        if (event.key === 'ArrowDown' && menu.firstElementChild) {
          event.preventDefault()
          menu.firstElementChild.focus()
        }
      })
    })
  }
  var next = rules.children.length
  var template = rules.firstElementChild.cloneNode(true)
  Array.from(rules.children).forEach(initSearch)
  document.getElementById('addScopeRule').addEventListener('click', function () {
    if (rules.children.length >= 100) return
    var row = template.cloneNode(true)
    row.querySelectorAll('input, select').forEach(function (field) {
      field.name = field.name.replace(/rules\[\d+\]/, 'rules[' + next + ']')
      field.value = field.name.endsWith('[effect]') ? 'ALLOW' : ''
    })
    next++
    rules.appendChild(row)
    initSearch(row)
  })
  rules.addEventListener('click', function (event) {
    if (event.target.closest('.scope-rule-remove') && rules.children.length > 1) {
      event.target.closest('.scope-rule').remove()
    }
  })
})()
