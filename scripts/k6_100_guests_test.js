import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

// Métricas personalizadas
const checkinSuccessRate = new Rate('checkin_success_rate');
const celebrateSuccessRate = new Rate('celebrate_success_rate');
const memoryInteractionSuccessRate = new Rate('memory_interaction_success_rate');
const commentsPosted = new Counter('comments_posted_total');

export const options = {
  stages: [
    { duration: '8s', target: 50 },   // Subida gradual a 50 usuarios
    { duration: '8s', target: 100 },  // Subida a 100 usuarios concurrentes
    { duration: '20s', target: 100 }, // Carga sostenida de 100 usuarios simultáneos interactuando
    { duration: '6s', target: 0 },    // Bajada ordenada
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],                 // Más del 95% de solicitudes exitosas bajo estrés
    http_req_duration: ['p(95)<3500'],              // 95% de peticiones atendidas adecuadamente
    checkin_success_rate: ['rate>0.95'],            // Check-in de 100 invitados > 95%
    celebrate_success_rate: ['rate>0.95'],          // Celebración en vivo > 95%
    memory_interaction_success_rate: ['rate>0.95'], // Interacciones con el muro > 95%
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8001/api/v1';

export function setup() {
  // Verificar que el backend esté arriba y obtener primer recuerdo
  const res = http.get(`${BASE_URL}/memories?page=1`);
  check(res, {
    'setup memories reachable': (r) => r.status === 200,
  });

  let memoryId = 'mem-seed-1794933605-7mod7';
  try {
    const json = JSON.parse(res.body);
    if (json.data && json.data.length > 0) {
      memoryId = json.data[0].id;
    }
  } catch (e) {
    // fallback
  }

  return { memoryId };
}

export default function (data) {
  const vuId = __VU; // 1 a 100
  const deviceId = `vu-device-${vuId}`;
  const tableIndex = ((vuId - 1) % 6) + 1;
  const tableSlug = `mesa-0${tableIndex}`;
  const guestName = `Invitado_${vuId}`;
  let actorKey = `${tableSlug}__invitado-${vuId}`;

  const baseHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-Device-Id': deviceId,
  };

  // 1. Consulta inicial del evento (Estado, dbScope, epoch)
  {
    const eventRes = http.get(`${BASE_URL}/event`, { headers: baseHeaders });
    check(eventRes, {
      'event status 200': (r) => r.status === 200,
      'dbScope present': (r) => r.body.includes('dbScope'),
    });
  }

  // 2. Consulta de mesas disponibles
  {
    const tablesRes = http.get(`${BASE_URL}/tables`, { headers: baseHeaders });
    check(tablesRes, {
      'tables status 200': (r) => r.status === 200,
      'has 6 tables': (r) => r.body.includes('mesa-06'),
    });
  }

  // 3. Check-in de invitado a su mesa (1 dispositivo = 1 mesa)
  {
    const checkinPayload = JSON.stringify({
      eventCode: 'XV-NATALIA',
      tableCode: tableSlug,
      guestName: guestName,
      deviceId: deviceId,
      candidateFingerprints: [`fp-vu-${vuId}`],
    });

    const checkinRes = http.post(`${BASE_URL}/checkin`, checkinPayload, { headers: baseHeaders });
    const isOk = checkinRes.status === 200 || checkinRes.status === 201;
    checkinSuccessRate.add(isOk);

    check(checkinRes, {
      'checkin accepted (200 or 201)': () => isOk,
    });

    if (isOk) {
      try {
        const body = JSON.parse(checkinRes.body);
        if (body.data && body.data.actorKey) {
          actorKey = body.data.actorKey;
        }
      } catch (e) {}
    }
  }

  const authenticatedHeaders = Object.assign({}, baseHeaders, {
    'X-Actor-Key': actorKey,
  });

  // 4. Consulta del muro de recuerdos (Feed)
  {
    const feedRes = http.get(`${BASE_URL}/memories?page=1`, { headers: authenticatedHeaders });
    check(feedRes, {
      'feed status 200': (r) => r.status === 200,
      'meta total is valid': (r) => r.body.includes('meta'),
    });
  }

  // 5. Celebración global interactiva (Toggle Celebrate)
  {
    const celebratePayload = JSON.stringify({ delta: 1 });
    const celebrateRes = http.post(`${BASE_URL}/event/celebrate`, celebratePayload, {
      headers: authenticatedHeaders,
    });

    const isOk = celebrateRes.status === 200;
    celebrateSuccessRate.add(isOk);
    check(celebrateRes, {
      'celebrate status 200': () => isOk,
    });
  }

  // 6. Interacciones con el recuerdo compartido
  if (data && data.memoryId) {
    const memoryId = data.memoryId;

    // 6a. Compartir recuerdo
    const shareRes = http.post(`${BASE_URL}/memories/${memoryId}/shares`, null, {
      headers: authenticatedHeaders,
    });
    const shareOk = shareRes.status === 200;
    memoryInteractionSuccessRate.add(shareOk);
    check(shareRes, {
      'share memory status 200': () => shareOk,
    });

    // 6b. Reaccionar al recuerdo ('fires' o 'likes')
    const choice = vuId % 2 === 0 ? 'fires' : 'likes';
    const reactPayload = JSON.stringify({
      actorKey: actorKey,
      deviceId: deviceId,
      choice: choice,
    });
    const reactRes = http.post(
      `${BASE_URL}/memories/${memoryId}/reactions/toggle`,
      reactPayload,
      { headers: authenticatedHeaders }
    );
    const reactOk = reactRes.status === 200;
    memoryInteractionSuccessRate.add(reactOk);
    check(reactRes, {
      'reaction toggle status 200': () => reactOk,
    });

    // 6c. Comentar en el recuerdo (1 cada 3 iteraciones para balancear)
    if (__ITER % 3 === 0) {
      const commentPayload = JSON.stringify({
        text: `¡Feliz XV Natalia! Saludos desde la ${tableSlug} - ${guestName} (iter ${__ITER})`,
        author: guestName,
        table: tableSlug,
        deviceId: deviceId,
        actorKey: actorKey,
      });

      const commentRes = http.post(
        `${BASE_URL}/memories/${memoryId}/comments`,
        commentPayload,
        { headers: authenticatedHeaders }
      );

      const commentOk = commentRes.status === 201;
      if (commentOk) {
        commentsPosted.add(1);
      }
      check(commentRes, {
        'comment created status 201': () => commentOk,
      });
    }
  }

  // Pausa realista entre 0.8 y 1.8 segundos antes de la siguiente interacción
  sleep(0.8 + Math.random() * 1.0);
}

export function teardown(data) {
  // Verificación final del estado
  const eventRes = http.get(`${BASE_URL}/event`);
  const memoriesRes = http.get(`${BASE_URL}/memories?page=1`);
  console.log(`[k6 Teardown] Carga completada. Respuestas finales: Event Status ${eventRes.status}, Memories Status ${memoriesRes.status}`);
}
