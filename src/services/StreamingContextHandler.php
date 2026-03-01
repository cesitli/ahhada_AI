// src/services/StreamingContextHandler.php
class StreamingContextHandler {
    
    public function processLargeConversation($conversationId) {
        // 1. Konuşmayı veritabanından al (chunk chunk)
        $messages = $this->getConversationChunks($conversationId, 100); // 100'er mesaj
        
        // 2. Context'i stream et
        $context = '';
        $results = [];
        
        foreach ($messages as $chunk) {
            $chunkContext = $this->buildChunkContext($context, $chunk);
            
            // Token kontrolü
            if ($this->exceedsLimit($chunkContext)) {
                // Context'i optimize et
                $context = $this->optimizeContext($context);
                $chunkContext = $this->buildChunkContext($context, $chunk);
            }
            
            // AI processing (bu kısım async olabilir)
            $result = $this->processWithAI($chunkContext);
            
            // Context'i güncelle
            $context = $this->updateContext($context, $chunk, $result);
            $results[] = $result;
        }
        
        return $this->aggregateResults($results);
    }
    
    private function getConversationChunks($conversationId, $chunkSize) {
        // Veritabanından chunk'lar halinde veri çek
        $total = Message::where('conversation_id', $conversationId)->count();
        $chunks = [];
        
        for ($offset = 0; $offset < $total; $offset += $chunkSize) {
            $chunks[] = Message::where('conversation_id', $conversationId)
                ->orderBy('created_at')
                ->offset($offset)
                ->limit($chunkSize)
                ->get();
        }
        
        return $chunks;
    }
}