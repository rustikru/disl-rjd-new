(function () {
  'use strict'

  var groupsKey = 'rjd.sidebar.groups'

  function readGroups() {
    try {
      return JSON.parse(localStorage.getItem(groupsKey) || '{}') || {}
    } catch (error) {
      return {}
    }
  }

  function saveGroups(groups) {
    try {
      localStorage.setItem(groupsKey, JSON.stringify(groups))
    } catch (error) {}
  }

  function prepareGroup(group, index, state) {
    var label = group.querySelector('.nav-group-label')
    if (!label) return
    var labelText = label.textContent.trim()
    var groupKey = labelText || 'group-' + index
    var items = group.querySelector('.nav-group-items')
    if (!items) {
      items = document.createElement('div')
      items.className = 'nav-group-items'
      Array.from(group.children).forEach(function (child) {
        if (child.classList && child.classList.contains('nav-item')) items.appendChild(child)
      })
      group.appendChild(items)
    }

    if (!label.classList.contains('nav-group-toggle')) {
      var button = document.createElement('button')
      button.type = 'button'
      button.className = 'nav-group-label nav-group-toggle'
      button.innerHTML = '<span class="nav-group-title"></span><span class="nav-group-caret" aria-hidden="true"></span>'
      button.querySelector('.nav-group-title').textContent = labelText
      label.replaceWith(button)
      label = button
    }

    var hasActiveItem = !!items.querySelector('.nav-item.active')
    var closed = !hasActiveItem && state[groupKey] === true
    group.classList.toggle('is-closed', closed)
    label.setAttribute('aria-expanded', closed ? 'false' : 'true')
    label.addEventListener('click', function () {
      var nextClosed = !group.classList.contains('is-closed')
      group.classList.toggle('is-closed', nextClosed)
      label.setAttribute('aria-expanded', nextClosed ? 'false' : 'true')
      state[groupKey] = nextClosed
      saveGroups(state)
    })
  }

  function openActiveGroup(sidebar) {
    var active = sidebar.querySelector('.nav-item.active')
    if (!active) return
    var group = active.closest('.nav-group')
    if (!group) return
    group.classList.remove('is-closed')
    var button = group.querySelector('.nav-group-toggle')
    if (button) button.setAttribute('aria-expanded', 'true')
  }

  function initSidebar(sidebar) {
    if (sidebar.dataset.collapsibleReady === '1') return
    if (!sidebar.querySelector('.nav-group')) return
    sidebar.dataset.collapsibleReady = '1'

    var state = readGroups()
    sidebar.querySelectorAll('.nav-group').forEach(function (group, index) {
      prepareGroup(group, index, state)
    })

    document.addEventListener('sidebar:active', function () { openActiveGroup(sidebar) })
  }

  function init() {
    document.querySelectorAll('.sidebar').forEach(initSidebar)
  }

  document.addEventListener('sidebar:rendered', init)

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }
})()
