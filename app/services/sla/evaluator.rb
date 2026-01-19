# frozen_string_literal: true

module Sla
  class Evaluator
    def self.call(ticket)
      return end_all_cycles(ticket) if ticket.closed?

      case ticket.last_reply_from&.to_sym
      when :customer
        restart_sla(ticket)
      when :user
        pause_sla(ticket)
      end
    end

    def self.restart_sla(ticket)
      ticket.current_sla_cycle&.end!(reason: :customer_reply)
      ticket.start_sla_cycle!(reason: :customer_reply)
    end

    def self.pause_sla(ticket)
      ticket.current_sla_cycle&.pause!
    end

    def self.end_all_cycles(ticket)
      ticket.sla_cycles.active.find_each do |cycle|
        cycle.end!(reason: :closed)
      end
    end
  end
end
