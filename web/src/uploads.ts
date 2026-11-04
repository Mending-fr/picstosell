import { post, errorMessage } from './api'
import type { PickedPhoto } from './components/PhotoPicker.vue'
import type { Photo } from './types'

/**
 * Envoie les photos une par une (multipart) et met à jour leur état.
 * Retourne le nombre de photos envoyées avec succès.
 */
export async function uploadPhotos(vehicleId: number, list: PickedPhoto[]): Promise<number> {
  let ok = 0
  for (const p of list) {
    if (p.state === 'done') {
      ok++
      continue
    }
    p.state = 'sending'
    p.error = undefined
    const fd = new FormData()
    fd.append('file', p.file)
    if (p.slot === 'interior' || p.slot === 'detail') {
      fd.append('kind', p.slot)
    } else {
      fd.append('kind', 'exterior')
      fd.append('shot_key', p.slot)
    }
    try {
      await post<Photo>(`/vehicles/${vehicleId}/photos`, fd)
      p.state = 'done'
      ok++
    } catch (e) {
      p.state = 'error'
      p.error = errorMessage(e)
    }
  }
  return ok
}
