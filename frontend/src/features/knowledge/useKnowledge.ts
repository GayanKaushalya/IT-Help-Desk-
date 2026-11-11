import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  knowledgeService,
  type KnowledgeListParams,
  type KnowledgePayload,
} from '@/services/knowledgeService'

export const knowledgeKeys = {
  list: (params: KnowledgeListParams) => ['knowledge', 'list', params] as const,
  article: (id: string) => ['knowledge', 'article', id] as const,
}

export function useKnowledgeArticles(params: KnowledgeListParams) {
  return useQuery({
    queryKey: knowledgeKeys.list(params),
    queryFn: () => knowledgeService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useKnowledgeArticle(id: string | undefined) {
  return useQuery({
    queryKey: knowledgeKeys.article(id ?? ''),
    queryFn: () => knowledgeService.show(id as string),
    enabled: Boolean(id),
    retry: false,
  })
}

function useInvalidateKnowledge(articleId?: string) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: ['knowledge'] })
    if (articleId) {
      void queryClient.invalidateQueries({ queryKey: knowledgeKeys.article(articleId) })
    }
  }
}

export function useCreateKnowledgeArticle() {
  const invalidate = useInvalidateKnowledge()

  return useMutation({
    mutationFn: (payload: KnowledgePayload) => knowledgeService.create(payload),
    onSuccess: invalidate,
  })
}

export function useUpdateKnowledgeArticle(articleId: string) {
  const invalidate = useInvalidateKnowledge(articleId)

  return useMutation({
    mutationFn: (payload: Partial<KnowledgePayload>) => knowledgeService.update(articleId, payload),
    onSuccess: invalidate,
  })
}
