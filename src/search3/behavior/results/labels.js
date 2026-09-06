  function safe(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function textValue(value) {
    if (value == null) return '';
    if (typeof value === 'object') {
      return textValue(value.russianName || value.fullRussianName || value.name || value.title || '');
    }
    return String(value).trim();
  }

  function hotelId(hotel) {
    return String(hotel && hotel.id != null ? hotel.id : '');
  }

  function representativeTour(hotel) {
    var tours = hotel && Array.isArray(hotel.tours) ? hotel.tours : [];
    if (!tours.length) return null;
    return tours.slice().sort(function (a, b) {
      var left = Number(a && a.price || 0) || Number.MAX_SAFE_INTEGER;
      var right = Number(b && b.price || 0) || Number.MAX_SAFE_INTEGER;
      return left - right;
    })[0] || null;
  }

  function tourWord(count) {
    return plural(count, 'тур', 'тура', 'туров');
  }

  function plural(count, one, few, many) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function formatTourDate(value) {
    var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
    if (!match) return String(value || '').trim();
    var months = ['янв.', 'февр.', 'марта', 'апр.', 'мая', 'июня', 'июля', 'авг.', 'сент.', 'окт.', 'нояб.', 'дек.'];
    return String(Number(match[3])) + ' ' + months[Number(match[2]) - 1] + ' ' + match[1];
  }

  function mealLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var key = raw.toUpperCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    var labels = {
      'RO': 'Без питания',
      'ROOM ONLY': 'Без питания',
      'BB': 'Завтраки',
      'BREAKFAST': 'Завтраки',
      'HB': 'Завтрак и ужин',
      'HALF BOARD': 'Завтрак и ужин',
      'FB': 'Трёхразовое питание',
      'FULL BOARD': 'Трёхразовое питание',
      'AI': 'Всё включено',
      'ALL INCLUSIVE': 'Всё включено',
      'UAI': 'Ультра всё включено',
      'ULTRA ALL INCLUSIVE': 'Ультра всё включено'
    };
    return labels[key] || raw;
  }

  function guestCountLabel(adults, children) {
    adults = Math.max(1, Number(adults) || 2);
    children = Math.max(0, Number(children) || 0);
    var label = adults + ' ' + plural(adults, 'взрослый', 'взрослых', 'взрослых');
    if (children > 0) label += ' и ' + children + ' ' + plural(children, 'ребёнок', 'ребёнка', 'детей');
    return label;
  }

  function roomLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var key = raw.toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    var labels = {
      'standard': 'Стандартный номер',
      'standard room': 'Стандартный номер',
      'std': 'Стандартный номер',
      'std room': 'Стандартный номер',
      'std room without air conditioner': 'Стандартный номер без кондиционера'
    };
    return labels[key] || raw;
  }

  function placementLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var labels = {
      'SGL': 'Одноместное',
      'DBL': 'Двухместное',
      'TRPL': 'Трёхместное',
      'QUAD': 'Четырёхместное'
    };
    return labels[raw.toUpperCase()] || raw;
  }

  function guestLabel() {
    var form = document.getElementById('tourSearch');
    var adults = Number(form && form.elements && form.elements.count_people && form.elements.count_people.value || 2) || 2;
    var children = Number(form && form.elements && form.elements.child_count && form.elements.child_count.value || 0) || 0;
    return guestCountLabel(adults, children);
  }
