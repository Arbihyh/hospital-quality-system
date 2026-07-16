const key = 'menu'

export function setMenu(list) {
  return localStorage.setItem(key, JSON.stringify(list))
}

export function getMenu() {
  const data = localStorage.getItem(key)
  return JSON.parse(data)
}

export function removeMenu() {
  return localStorage.removeItem(key)
}
