import { apiClient } from '@/services/apiClient'
import type { PaginationMeta } from '@/types/api'
import type { KnowledgeArticle } from '@/types/models'

export interface KnowledgeListParams {
  search?: string
  topic?: string
  published?: '' | '1' | '0'
  page?: number
  per_page?: number
}

export interface KnowledgeListResult {
  data: KnowledgeArticle[]
  meta: PaginationMeta
}

export interface KnowledgePayload {
  title: string
  summary?: string | null
  body: string
  topic?: string | null
  is_published?: boolean
}

function compact(params: object): Record<string, string | number> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== ''),
  ) as Record<string, string | number>
}

export const knowledgeService = {
  list: async (params: KnowledgeListParams = {}): Promise<KnowledgeListResult> => {
    const response = await apiClient.getRaw<KnowledgeArticle[]>('/knowledge-articles', {
      params: compact({
        search: params.search,
        topic: params.topic,
        published: params.published,
        page: params.page,
        per_page: params.per_page,
      }),
    })

    return {
      data: response.data,
      meta: response.meta ?? {
        current_page: 1,
        per_page: 15,
        total: response.data.length,
        last_page: 1,
        from: response.data.length > 0 ? 1 : null,
        to: response.data.length > 0 ? response.data.length : null,
      },
    }
  },

  show: (id: number | string): Promise<KnowledgeArticle> =>
    apiClient.get<KnowledgeArticle>(`/knowledge-articles/${encodeURIComponent(String(id))}`),

  create: (payload: KnowledgePayload): Promise<KnowledgeArticle> =>
    apiClient.post<KnowledgeArticle>('/knowledge-articles', payload),

  update: (id: number | string, payload: Partial<KnowledgePayload>): Promise<KnowledgeArticle> =>
    apiClient.patch<KnowledgeArticle>(`/knowledge-articles/${encodeURIComponent(String(id))}`, payload),
}
