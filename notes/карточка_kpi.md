Хочу сделать рендер произвольной карточки KPI. Т.е передавать свой произвольный массив, например как var KPI_BOARDS = {// GET /api/dashboard}
может написать универсальную функцию, может даже ее модифицировать.
function showDashKpi(data) {
var cards = KPI_BOARDS.dashboard.cards(data)
$('#kpiGrid').html(cards.map(kpiCard).join(''))
} и потом использовать по всюду.

Мысли такие по конфигу KPI карточки
KPI_BOARDS_NEW
dataUrl: BASE + '/api/kpi/kpiSummary', -- Данные для карточки
detailUrl: BASE + '/api/kpi/kpiDetail', -- Данные для карточки
Или использовать одну API, но потом ограничивать основной запрос уникальным параметром для этого KPI (атрибут в карточке)
API будет возвращать label, vallue, trend: makeTrend

Преложи свои варианты.
